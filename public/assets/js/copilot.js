(() => {
    'use strict';
    document.querySelectorAll('[data-copilot]').forEach((root) => {
        const launch = root.querySelector('.cn-copilot-launch');
        const panel = root.querySelector('.cn-copilot-panel');
        const messages = root.querySelector('.cn-copilot-messages');
        const form = root.querySelector('.cn-copilot-form');
        const input = form.querySelector('textarea');
        const setOpen = (open) => { root.classList.toggle('is-open', open); launch.setAttribute('aria-expanded', String(open)); panel.setAttribute('aria-hidden', String(!open)); if (open) input.focus(); };
        const add = (text, who, error = false) => {
            const item = document.createElement('div'); item.className = `cn-copilot-message ${who}${error ? ' error' : ''}`;
            if (who === 'assistant' && !error) {
                const pattern = /https?:\/\/[^\s)]+/g; let cursor = 0; let match;
                while ((match = pattern.exec(text)) !== null) {
                    let url; try { url = new URL(match[0]); } catch (ignored) { continue; }
                    if (url.origin !== location.origin || !/^\/jobseeker\/jobs\/\d+$/.test(url.pathname)) continue;
                    item.append(document.createTextNode(text.slice(cursor, match.index)));
                    const link = document.createElement('a'); link.href = url.href; link.textContent = 'View job'; link.rel = 'noopener'; item.append(link); cursor = pattern.lastIndex;
                }
                item.append(document.createTextNode(text.slice(cursor)));
            } else item.textContent = text;
            messages.append(item); messages.scrollTop = messages.scrollHeight; return item;
        };
        const BUSY = 'The AI assistant is temporarily busy. Please try again in a moment.';
        const UNEXPECTED = 'The AI assistant returned an unexpected response. Please try again.';
        // Messages for failures produced by Laravel itself (not the Copilot controller), whose
        // framework wording is not meant for end users.
        const STATUS_MESSAGES = {
            401: 'Your session has ended. Please sign in again to use the AI assistant.',
            403: 'You do not have access to this AI assistant.',
            419: 'Your session has expired. Please refresh the page and try again.',
            429: 'You are sending messages too quickly. Please wait a minute and try again.',
        };
        // Server request budget is at most 24 s; give up a little after that.
        const CLIENT_TIMEOUT_MS = 28000;
        const fallbackFor = (status) => STATUS_MESSAGES[status] || (status >= 500 || status === 0 ? BUSY : UNEXPECTED);
        const readJsonResponse = async (response) => {
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            if (!contentType.includes('application/json')) {
                // HTML error pages (proxy timeouts, PHP fatals) and empty bodies are never parsed as JSON.
                throw new Error(fallbackFor(response.ok ? -1 : response.status));
            }
            let data;
            try { data = await response.json(); }
            catch (ignored) { throw new Error(fallbackFor(response.ok ? -1 : response.status)); }
            if (!data || typeof data !== 'object') throw new Error(fallbackFor(response.ok ? -1 : response.status));
            if (!response.ok) {
                // Our controller and middleware send user-safe messages; framework 401/419/429 wording is replaced.
                const providerMessageIsSafe = response.headers.get('x-copilot-safe-error') === '1';
                const serverMessage = typeof data.message === 'string' && (response.status < 500 || providerMessageIsSafe) ? data.message.trim() : '';
                throw new Error(STATUS_MESSAGES[response.status] || serverMessage || fallbackFor(response.status));
            }
            if (typeof data.reply !== 'string' || data.reply.trim() === '') throw new Error(UNEXPECTED);
            return data;
        };
        const sendMessage = async (message) => {
            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            const timer = controller ? setTimeout(() => controller.abort(), CLIENT_TIMEOUT_MS) : null;
            let response;
            try {
                response = await fetch(root.dataset.messageUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf, 'Accept': 'application/json' }, body: JSON.stringify({ message }), signal: controller ? controller.signal : undefined });
            } catch (ignored) {
                // Network failure, offline, or client-side timeout: never surface raw browser errors.
                throw new Error(BUSY);
            } finally {
                if (timer) clearTimeout(timer);
            }
            return readJsonResponse(response);
        };
        launch.addEventListener('click', () => setOpen(true));
        root.querySelector('[data-copilot-close]').addEventListener('click', () => setOpen(false));
        root.querySelectorAll('.cn-copilot-suggestions button').forEach((button) => button.addEventListener('click', () => { input.value = button.textContent; input.focus(); }));
        root.querySelector('[data-copilot-new]').addEventListener('click', async () => {
            try {
                const response = await fetch(root.dataset.resetUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': root.dataset.csrf, 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('reset failed');
            } catch (ignored) { add('Could not start a new conversation right now. Please try again shortly.', 'assistant', true); return; }
            messages.replaceChildren(); add('New conversation started. What can I help with?', 'assistant');
        });
        let sending = false; // guards against double submits (Enter + click) while a request is in flight
        form.addEventListener('submit', async (event) => {
            event.preventDefault(); const message = input.value.trim(); const button = form.querySelector('button');
            if (!message || sending || button.disabled) return;
            sending = true; add(message, 'user'); input.value = ''; button.disabled = true; root.setAttribute('aria-busy', 'true');
            const loading = add('Thinking…', 'assistant');
            try { const data = await sendMessage(message); loading.remove(); add(data.reply, 'assistant'); }
            catch (error) { loading.remove(); add((error && error.message) || BUSY, 'assistant', true); }
            finally { sending = false; loading.remove(); button.disabled = false; root.setAttribute('aria-busy', 'false'); input.focus(); }
        });
        input.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && root.classList.contains('is-open')) setOpen(false); });
    });
})();
