document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.cn-menu-toggle');
    const sidebar = document.getElementById('roleSidebar');
    const backdrop = document.querySelector('.cn-sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    let previousFocus = null;
    const close = () => {
        document.body.classList.remove('cn-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        if (previousFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => {
        if (document.body.classList.contains('cn-sidebar-open')) {
            close();
            return;
        }
        previousFocus = document.activeElement;
        document.body.classList.add('cn-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation');
        const firstLink = sidebar.querySelector('a, button');
        if (firstLink) firstLink.focus();
    });
    backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('cn-sidebar-open')) close();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1023 && document.body.classList.contains('cn-sidebar-open')) close();
    });
});
