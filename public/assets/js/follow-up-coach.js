/*
 * Application Follow-up Coach.
 *
 * The server decides every recommendation, draft and timeline entry; this script
 * only asks for them and paints what comes back. Handlers are delegated from
 * document and the file guards against a second load, so an AJAX region swap
 * cannot leave duplicate listeners behind.
 */
(function () {
    'use strict';

    if (window.__cnFollowUpReady) return;
    window.__cnFollowUpReady = true;

    function notify(type, message) {
        if (typeof window.showAlert === 'function') window.showAlert(type, message);
    }

    function cardOf(element) {
        return element.closest('[data-fu-app]');
    }

    function tokenOf(card) {
        return card ? card.getAttribute('data-fu-token') : null;
    }

    async function post(url, payload, token) {
        const body = new URLSearchParams();
        Object.entries(payload).forEach(([key, value]) => body.append(key, String(value)));
        if (token) body.append('_token', token);

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        let payloadOut = null;
        try {
            payloadOut = await response.json();
        } catch {
            throw new Error('The server returned an unreadable response. Please try again.');
        }

        if (!response.ok || payloadOut?.success === false) {
            throw new Error(payloadOut?.message || 'The request failed (' + response.status + ').');
        }

        return payloadOut || {};
    }

    function fold(card, name) {
        return card ? card.querySelector('[data-fu-fold="' + name + '"]') : null;
    }

    // ── Opening the draft from a card's suggested action ─────────────────────
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-fu-open]');
        if (!trigger) return;

        const card = cardOf(trigger);
        const details = fold(card, trigger.getAttribute('data-fu-open'));
        if (!details) return;

        details.open = true;
        const template = trigger.getAttribute('data-fu-template');
        const select = details.querySelector('[data-fu-template]');
        if (template && select) select.value = template;

        const generate = details.querySelector('[data-fu-generate]');
        if (generate) generate.focus();
    });

    // ── Message generator ────────────────────────────────────────────────────
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-fu-generate]');
        if (!button) return;

        const form = button.closest('[data-fu-message]');
        const card = cardOf(button);
        if (!form || !card) return;

        const wasLabel = button.textContent.trim();
        button.disabled = true;
        button.textContent = 'Drafting…';

        try {
            const draft = await post(form.action, { template: form.elements.template.value }, tokenOf(card));

            const subject = form.querySelector('[data-fu-subject]');
            const body = form.querySelector('[data-fu-body]');
            const note = form.querySelector('[data-fu-note]');

            if (subject) subject.value = draft.subject || '';
            if (body) body.value = draft.body || '';

            if (note) {
                note.textContent = draft.note || '';
                note.hidden = !draft.note;
            }

            notify('success', 'Draft ready. Edit it before you copy it.');
        } catch (error) {
            notify('error', error instanceof TypeError
                ? 'A network error occurred. Check your connection and try again.'
                : error.message);
        } finally {
            button.disabled = false;
            button.textContent = wasLabel;
        }
    });

    // ── Copying, without sending anything anywhere ───────────────────────────
    async function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }

        const helper = document.createElement('textarea');
        helper.value = value;
        helper.setAttribute('readonly', 'readonly');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.append(helper);
        helper.select();

        try {
            if (!document.execCommand('copy')) throw new Error('Clipboard copy was blocked.');
        } finally {
            helper.remove();
        }
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-fu-copy]');
        if (!button) return;

        const field = document.getElementById(button.getAttribute('data-fu-copy'));
        const value = field ? String(field.value || field.textContent || '').trim() : '';

        if (!value) {
            notify('warning', 'Generate a draft first, then copy it.');
            return;
        }

        try {
            await copyText(value);
            notify('success', 'Copied. Nothing has been sent.');
        } catch {
            notify('error', 'Your browser blocked the copy. Select the text and copy it manually.');
        }
    });

    // ── Interview checklist ──────────────────────────────────────────────────
    document.addEventListener('click', async (event) => {
        const check = event.target.closest('[data-fu-prep]');
        if (!check) return;

        const card = cardOf(check);
        const endpoint = card ? card.getAttribute('data-fu-prep-endpoint') : null;
        if (!endpoint) return;

        const wasDone = check.getAttribute('aria-pressed') === 'true';
        const isDone = !wasDone;
        setCheckState(check, isDone);
        updateProgress(card);

        check.disabled = true;

        try {
            const result = await post(endpoint, { item: check.getAttribute('data-fu-prep'), done: isDone ? 1 : 0 }, tokenOf(card));
            const done = Array.isArray(result.done) ? result.done : null;

            if (done) {
                card.querySelectorAll('[data-fu-prep]').forEach((button) => {
                    const state = done.includes(button.getAttribute('data-fu-prep'));
                    button.setAttribute('aria-pressed', state ? 'true' : 'false');
                    setCheckState(button, state);
                });
                updateProgress(card);
            }
        } catch (error) {
            setCheckState(check, wasDone);
            check.setAttribute('aria-pressed', wasDone ? 'true' : 'false');
            updateProgress(card);
            notify('error', error instanceof TypeError ? 'A network error occurred.' : error.message);
        } finally {
            check.disabled = false;
        }
    });

    function setCheckState(button, isDone) {
        button.classList.toggle('is-done', isDone);
        button.setAttribute('aria-pressed', isDone ? 'true' : 'false');

        const icon = button.querySelector('i');
        if (icon) icon.className = isDone ? 'far fa-square-check' : 'far fa-square';
    }

    function updateProgress(card) {
        const out = card ? card.querySelector('[data-fu-progress]') : null;
        if (!out) return;

        const total = card.querySelectorAll('[data-fu-prep]').length;
        const done = card.querySelectorAll('[data-fu-prep][aria-pressed="true"]').length;
        out.textContent = String(done);
        out.closest('.cn-fu-progress')?.setAttribute('aria-label', done + ' of ' + total + ' preparation items checked');
    }
})();
