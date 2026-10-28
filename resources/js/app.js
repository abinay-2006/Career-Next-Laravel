import '../css/button-loading.css';

(() => {
    'use strict';

    const activeButtons = new Map();
    let activeForms = new WeakSet();

    function loadingLabel(button, form) {
        if (button?.dataset.loadingText) return button.dataset.loadingText;
        if (form?.dataset.loadingText) return form.dataset.loadingText;

        const text = (button?.textContent || '').trim().toLowerCase();
        if (/delet|remove/.test(text)) return 'Deleting...';
        if (/approv|confirm/.test(text)) return 'Approving...';
        if (/reject|declin/.test(text)) return 'Rejecting...';
        if (/save|update|edit/.test(text)) return 'Saving...';
        if (/search|filter|find/.test(text)) return 'Searching...';
        if (/send|resend/.test(text)) return 'Sending...';
        if (/verif|otp|code/.test(text)) return 'Verifying...';
        if (/log ?out|sign ?out/.test(text)) return 'Signing out...';
        if (/sign ?in|log ?in/.test(text)) return 'Signing in...';
        if (/reset/.test(text)) return 'Resetting...';
        if (/upload/.test(text)) return 'Uploading...';
        if (/apply|application/.test(text)) return 'Applying...';
        if (/submit|post|register|create|continue/.test(text)) return 'Submitting...';
        return 'Loading...';
    }

    function start(button, label) {
        if (!button || activeButtons.has(button) || button.disabled) return null;

        const original = {
            children: [...button.childNodes],
            disabled: 'disabled' in button ? button.disabled : null,
            ariaBusy: button.getAttribute('aria-busy'),
            ariaDisabled: button.getAttribute('aria-disabled'),
            ariaLabel: button.getAttribute('aria-label'),
            minWidth: button.style.minWidth,
            pointerEvents: button.style.pointerEvents,
        };

        const width = button.getBoundingClientRect().width;
        if (width > 0) button.style.minWidth = `${width}px`;
        if ('disabled' in button) button.disabled = true;
        else {
            button.setAttribute('aria-disabled', 'true');
            button.style.pointerEvents = 'none';
        }
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-label', label || 'Loading...');
        const spinner = document.createElement('span');
        spinner.className = 'cn-button-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = label || 'Loading...';
        button.replaceChildren(spinner, text);

        const restore = () => {
            if (!activeButtons.has(button)) return;
            activeButtons.delete(button);
            button.replaceChildren(...original.children);
            if (original.disabled !== null) button.disabled = original.disabled;
            restoreAttribute(button, 'aria-busy', original.ariaBusy);
            restoreAttribute(button, 'aria-disabled', original.ariaDisabled);
            restoreAttribute(button, 'aria-label', original.ariaLabel);
            button.style.minWidth = original.minWidth;
            button.style.pointerEvents = original.pointerEvents;
        };

        activeButtons.set(button, restore);
        return restore;
    }

    function restoreAttribute(element, name, value) {
        if (value === null) element.removeAttribute(name);
        else element.setAttribute(name, value);
    }

    function notify(type, message) {
        if (typeof window.showAlert === 'function') window.showAlert(type, message);
        else window.alert(message);
    }

    function clearFieldError(form, field) {
        const control = form.elements.namedItem(field);
        if (!control) return;
        const controls = control instanceof RadioNodeList ? [...control] : [control];
        controls.forEach((element) => {
            element.removeAttribute('aria-invalid');
            element.classList.remove('cn-field-invalid');
        });
        const error = form.querySelector(`[data-cn-error-for="${CSS.escape(field)}"]`);
        if (error) {
            controls.forEach((element) => {
                const describedBy = new Set((element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                describedBy.delete(error.id);
                if (describedBy.size) element.setAttribute('aria-describedby', [...describedBy].join(' '));
                else element.removeAttribute('aria-describedby');
            });
            error.remove();
        }
    }

    function showValidationErrors(form, errors) {
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const control = form.elements.namedItem(field);
            if (!control) return;

            const element = control instanceof RadioNodeList ? control[0] : control;
            if (!element) return;
            element.setAttribute('aria-invalid', 'true');
            element.classList.add('cn-field-invalid');

            let error = form.querySelector(`[data-cn-error-for="${CSS.escape(field)}"]`);
            if (!error) {
                error = document.createElement('span');
                error.className = 'cn-field-error';
                error.dataset.cnErrorFor = field;
                error.id = `cn-error-${Math.random().toString(36).slice(2)}`;
                element.insertAdjacentElement('afterend', error);
            }
            error.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            const describedBy = new Set((element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
            describedBy.add(error.id);
            element.setAttribute('aria-describedby', [...describedBy].join(' '));
        });
    }

    async function replaceRegion(url, selector, { updateHistory = false } = {}) {
        const targetSelector = selector || '[data-ajax-region]';
        const response = await fetch(url, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const finalUrl = new URL(response.url);
        const requestedUrl = new URL(url, window.location.href);
        if (!response.ok || (response.redirected
            && (finalUrl.origin !== requestedUrl.origin || finalUrl.pathname !== requestedUrl.pathname))) {
            throw new Error(response.status === 401 || response.redirected
                ? 'Your session may have expired. Please sign in again.'
                : `The page could not be refreshed (${response.status}).`);
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('text/html')) {
            throw new Error('The server returned an unexpected page response.');
        }

        const html = await response.text();
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const current = document.querySelector(targetSelector);
        const next = parsed.querySelector(targetSelector);
        if (!current || !next) throw new Error('The page section could not be updated.');
        current.replaceWith(next);

        if (typeof window.Alpine?.initTree === 'function') window.Alpine.initTree(next);
        if (!document.querySelector('.modal.show, .modal-overlay.open, .modal-overlay.show, .profile-modal-overlay.show')) {
            document.body.style.overflow = '';
        }
        document.dispatchEvent(new CustomEvent('cn:region-updated', { detail: { selector: targetSelector } }));
        if (updateHistory) window.history.pushState({}, '', finalUrl.href);
        return response;
    }

    async function updateAfterAjax(form, payload) {
        if (form.dataset.ajaxMarkApplied) {
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                activeButtons.get(button)?.();
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                button.textContent = form.dataset.ajaxMarkApplied;
            }
        }
        if (form.hasAttribute('data-ajax-remove-row')) form.closest('tr')?.remove();
        if (form.dataset.ajaxStatusTarget && payload?.status) {
            const status = document.querySelector(form.dataset.ajaxStatusTarget);
            if (status) {
                status.textContent = payload.status;
                status.className = `${form.dataset.ajaxStatusBase || ''} ${String(payload.status).toLowerCase()}`.trim();
            }
        }
        if (form.dataset.ajaxClose) {
            const modal = document.querySelector(form.dataset.ajaxClose);
            modal?.classList.remove('open', 'show');
            if (modal?.classList.contains('modal')) window.bootstrap?.Modal.getInstance(modal)?.hide();
            if (!document.querySelector('.modal.show, .modal-overlay.open, .modal-overlay.show')) document.body.style.overflow = '';
        }
        form.dispatchEvent(new CustomEvent('cn:ajax-success', { bubbles: true, detail: payload || {} }));
        if (payload?.refresh && form.dataset.ajaxRefreshTarget) {
            try {
                await replaceRegion(window.location.href, form.dataset.ajaxRefreshTarget);
            } catch (error) {
                notify('error', `Saved, but the page section could not refresh: ${error.message}`);
            }
        }
    }

    function makeFormData(form, button) {
        if (button) {
            try { return new FormData(form, button); } catch { /* older browser fallback */ }
        }
        const data = new FormData(form);
        if (button?.name && !data.has(button.name)) data.append(button.name, button.value);
        return data;
    }

    async function submitHtmlForm(form, button) {
        const url = new URL(form.action || window.location.href, window.location.href);
        url.search = '';
        makeFormData(form, button).forEach((value, key) => {
            if (typeof value === 'string') url.searchParams.append(key, value);
        });
        await replaceRegion(url, form.dataset.ajaxTarget, { updateHistory: true });
    }

    async function submitAjax(form, button, restoreButton) {
        if (form.hasAttribute('data-ajax-html')) {
            try {
                await submitHtmlForm(form, button);
            } catch (error) {
                notify('error', error.message || 'The results could not be loaded.');
            } finally {
                activeForms.delete(form);
                if (restoreButton) restoreButton();
            }
            return;
        }

        const method = (form.method || 'GET').toUpperCase();
        const url = new URL(form.action || window.location.href, window.location.href);
        const token = form.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.content;
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (token) headers['X-CSRF-TOKEN'] = token;

        try {
            let body;
            if (['GET', 'HEAD'].includes(method)) {
                makeFormData(form, button).forEach((value, key) => {
                    if (typeof value === 'string') url.searchParams.append(key, value);
                });
            } else {
                body = makeFormData(form, button);
            }

            const response = await fetch(url, {
                method,
                headers,
                credentials: 'same-origin',
                body,
            });

            const contentType = response.headers.get('content-type') || '';
            const isJson = contentType.includes('application/json');
            let payload = null;
            if (isJson) {
                try {
                    payload = await response.json();
                } catch {
                    throw new Error('The server returned an unreadable response. Please try again.');
                }
            }

            if (!response.ok) {
                if (response.status === 422 && payload?.errors) {
                    showValidationErrors(form, payload.errors);
                }
                const messages = {
                    401: 'Your session has expired. Please sign in again.',
                    403: 'You are not authorized to perform this action.',
                    404: 'The requested item could not be found.',
                    419: 'Your session expired. Refresh the page and try again.',
                    429: 'Too many attempts. Please wait and try again.',
                };
                throw new Error(payload?.message || messages[response.status] || `The request failed (${response.status}).`);
            }

            if (response.redirected) throw new Error('The server redirected this AJAX request. Please retry or sign in again.');
            if (!isJson) throw new Error('The server returned an unexpected response. Please try again.');
            if (payload?.success === false) throw new Error(payload.message || 'The operation could not be completed.');

            if (payload?.message) notify('success', payload.message);
            await updateAfterAjax(form, payload);
            if (form.hasAttribute('data-ajax-reset')) {
                form.reset();
                form.querySelectorAll('[data-cn-error-for]').forEach((error) => clearFieldError(form, error.dataset.cnErrorFor));
            }
            if (payload?.redirect && form.hasAttribute('data-ajax-allow-navigation')) window.location.assign(payload.redirect);
        } catch (error) {
            notify('error', error instanceof TypeError
                ? 'A network error occurred. Check your connection and try again.'
                : (error.message || 'The operation could not be completed.'));
        } finally {
            activeForms.delete(form);
            if (restoreButton) restoreButton();
        }
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (event.defaultPrevented) return;
        if (form.dataset.loadingManaged === 'true') return;
        if (activeForms.has(form)) {
            event.preventDefault();
            return;
        }

        const button = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        const existingRestore = activeButtons.get(button);
        const ajaxForm = form.hasAttribute('data-ajax') || form.hasAttribute('data-ajax-html');
        if ((!button && !ajaxForm) || (button?.disabled && !existingRestore)) return;
        const restore = button ? (existingRestore || start(button, loadingLabel(button, form))) : null;
        if (ajaxForm) {
            event.preventDefault();
            if (activeForms.has(form)) return;
            activeForms.add(form);
            void submitAjax(form, button, restore);
        } else {
            activeForms.add(form);
        }
    });

    document.addEventListener('input', (event) => {
        const form = event.target.form;
        if (!form) return;
        const field = event.target.name;
        if (field) clearFieldError(form, field);
    });

    document.addEventListener('change', (event) => {
        const control = event.target;
        if (!(control instanceof HTMLSelectElement)) return;
        if (control.hasAttribute('data-ajax-url')) {
            const wasDisabled = control.disabled;
            const wasBusy = control.getAttribute('aria-busy');
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
            void replaceRegion(control.value, '[data-ajax-region]', { updateHistory: true })
                .catch((error) => notify('error', error.message))
                .finally(() => {
                    control.disabled = wasDisabled;
                    restoreAttribute(control, 'aria-busy', wasBusy);
                });
            return;
        }
        const form = control.closest('form[data-ajax-submit-on-change]');
        if (form) form.requestSubmit();
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const element = event.target.closest('a[href], [data-loading]');
        if (!element || element.closest('form') || event.defaultPrevented) return;
        if (element instanceof HTMLAnchorElement) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                || element.target === '_blank' || element.hasAttribute('download')) return;
            const destination = new URL(element.href, window.location.href);
            if (destination.origin !== window.location.origin) return;
            if (destination.pathname === window.location.pathname
                && destination.search === window.location.search && destination.hash) return;
            const region = document.querySelector('[data-ajax-region]');
            if (region && destination.pathname === window.location.pathname
                && destination.search !== window.location.search) {
                event.preventDefault();
                const restore = start(element, element.dataset.loadingText || loadingLabel(element));
                void replaceRegion(destination.href, '[data-ajax-region]', { updateHistory: true })
                    .catch((error) => notify('error', error.message))
                    .finally(() => restore?.());
                return;
            }
        }
        start(element, element.dataset.loadingText || loadingLabel(element));
    });

    window.addEventListener('pageshow', () => {
        activeButtons.forEach((restore) => restore());
        activeForms = new WeakSet();
    });
    window.addEventListener('popstate', () => {
        if (document.querySelector('[data-ajax-region]')) {
            void replaceRegion(window.location.href, '[data-ajax-region]')
                .catch((error) => notify('error', error.message));
        }
    });

    window.CareerNextButtonLoading = { start, loadingLabel };
})();
