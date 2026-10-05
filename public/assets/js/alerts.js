(function () {
    'use strict';

    const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    function container() {
        let host = document.getElementById('alert-container');
        if (host) return host;
        if (!document.body) return null;
        host = document.createElement('div');
        host.id = 'alert-container';
        host.className = 'cn-alert-container';
        host.setAttribute('aria-label', 'Notifications');
        document.body.prepend(host);
        return host;
    }

    function dismiss(alert) {
        if (!alert || alert.dataset.closing === 'true') return;
        alert.dataset.closing = 'true';
        alert.classList.add('cn-alert-hiding');
        window.setTimeout(() => alert.remove(), 220);
    }

    function initialize(alert) {
        if (alert.dataset.alertInitialized === 'true') return;
        alert.dataset.alertInitialized = 'true';
        const close = alert.querySelector('[data-alert-close]');
        if (close) close.addEventListener('click', () => dismiss(alert));

        let timer;
        const duration = Number(alert.dataset.alertDuration || 0);
        if (duration > 0) {
            const start = () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => dismiss(alert), duration);
            };
            const pause = () => window.clearTimeout(timer);
            alert.addEventListener('mouseenter', pause);
            alert.addEventListener('focusin', pause);
            alert.addEventListener('mouseleave', start);
            alert.addEventListener('focusout', start);
            start();
        }
    }

    function showAlert(type, message, options) {
        if (!document.body) return null;
        options = options || {};
        type = Object.prototype.hasOwnProperty.call(icons, type) ? type : 'info';
        const host = container();
        if (!host) return null;

        const alert = document.createElement('div');
        alert.className = 'cn-alert cn-alert-' + type;
        alert.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
        alert.setAttribute('aria-live', type === 'error' || type === 'warning' ? 'assertive' : 'polite');
        alert.dataset.alert = '';
        const icon = document.createElement('i');
        icon.className = 'fa-solid ' + icons[type] + ' cn-alert-icon';
        icon.setAttribute('aria-hidden', 'true');
        alert.appendChild(icon);

        const content = document.createElement('div');
        content.className = 'cn-alert-content';
        if (options.title) {
            const title = document.createElement('strong');
            title.className = 'cn-alert-title';
            title.textContent = options.title;
            content.appendChild(title);
        }
        const text = document.createElement('span');
        text.className = 'cn-alert-message';
        text.textContent = message == null ? '' : String(message);
        content.appendChild(text);
        alert.appendChild(content);

        const dismissible = options.dismissible !== false;
        if (dismissible) {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'cn-alert-close';
            close.dataset.alertClose = '';
            close.setAttribute('aria-label', 'Dismiss ' + type + ' message');
            const closeIcon = document.createElement('i');
            closeIcon.className = 'fa-solid fa-xmark';
            closeIcon.setAttribute('aria-hidden', 'true');
            close.appendChild(closeIcon);
            alert.appendChild(close);
        }

        const duration = options.duration === undefined
            ? ({ success: 4500, info: 5000, warning: 0, error: 0 }[type])
            : Math.max(0, Number(options.duration) || 0);
        alert.dataset.alertDuration = String(duration);
        host.appendChild(alert);
        initialize(alert);
        return alert;
    }

    window.showAlert = showAlert;
    function initAlerts() {
        document.querySelectorAll('[data-alert]').forEach(initialize);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAlerts, { once: true });
    } else {
        initAlerts();
    }
})();
