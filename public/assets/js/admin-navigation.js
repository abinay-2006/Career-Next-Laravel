document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.cn-admin-menu-toggle');
    const drawer = document.getElementById('adminMobileDrawer');
    const closeControls = document.querySelectorAll('[data-admin-drawer-close]');
    if (!toggle || !drawer || closeControls.length === 0) return;

    const mobileLayout = window.matchMedia('(max-width: 1023.98px)');
    let previousFocus = toggle;

    const setOpen = (open, { restoreFocus = true } = {}) => {
        document.body.classList.toggle('cn-admin-drawer-open', open);
        drawer.inert = !open;
        drawer.setAttribute('aria-hidden', String(!open));
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close Admin navigation' : 'Open Admin navigation');

        if (open) {
            previousFocus = document.activeElement;
            const firstItem = drawer.querySelector('.cn-admin-nav-link, button, a[href]');
            if (firstItem) firstItem.focus();
        } else if (restoreFocus && previousFocus?.isConnected && mobileLayout.matches) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => {
        if (!mobileLayout.matches) return;
        setOpen(!document.body.classList.contains('cn-admin-drawer-open'));
    });

    closeControls.forEach(control => control.addEventListener('click', () => setOpen(false)));
    drawer.querySelectorAll('.cn-admin-nav-link').forEach(link => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', event => {
        if (!document.body.classList.contains('cn-admin-drawer-open')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            return;
        }

        if (event.key !== 'Tab') return;
        const focusable = [...drawer.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')];
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    const closeOnDesktop = event => {
        if (!event.matches && document.body.classList.contains('cn-admin-drawer-open')) {
            setOpen(false, { restoreFocus: false });
        }
    };
    if (mobileLayout.addEventListener) mobileLayout.addEventListener('change', closeOnDesktop);
    else mobileLayout.addListener(closeOnDesktop);
});
