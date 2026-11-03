@props(['role'])
@php
    $roleTitle = ucfirst($role);
    $dashboardRoute = $role === 'employer' ? 'employer.dashboard' : 'jobseeker.index';
    $profileRoute = $role . '.profile.index';
    $headerAccount = Auth::guard($role)->user();
    $accountName = session('UserName');
    $accountType = session('UserType');
    $accountPhoto = $headerAccount ? \App\Support\ProfilePhoto::url($headerAccount->Photo) : null;
@endphp

<div class="cn-admin-shell">
    <aside class="cn-admin-sidebar" aria-label="{{ $roleTitle }} navigation">
        <a href="{{ route($dashboardRoute) }}" class="brand-logo cn-admin-brand"><img class="cn-logo-image cn-logo-image--sidebar" src="{{ asset('assets/images/career_next.png') }}" alt=""><span>Career Next</span></a>
        <div class="cn-admin-workspace-label">{{ $roleTitle }} workspace</div>
        <x-dashboard-sidebar :role="$role" />
    </aside>

    <div class="cn-admin-main-column">
        <header class="cn-admin-header">
            <button class="cn-admin-menu-toggle" type="button" aria-label="Open {{ $roleTitle }} navigation" aria-controls="{{ $role }}MobileDrawer" aria-expanded="false" data-role-drawer-toggle><i class="fas fa-bars" aria-hidden="true"></i></button>
            <a href="{{ route($dashboardRoute) }}" class="brand-logo cn-admin-brand cn-admin-mobile-brand"><img class="cn-logo-image cn-logo-image--mobile" src="{{ asset('assets/images/career_next.png') }}" alt=""><span>Career Next</span></a>
            <div class="cn-admin-theme-controls"><div class="theme-toggle-wrapper"><button class="theme-toggle" type="button" aria-label="Choose color theme"></button></div></div>
            <div class="cn-admin-header-account">
                <div class="profile-dropdown" id="profileDropdown">
                    <button type="button" class="profile-trigger" id="profileTrigger" aria-controls="{{ $role }}ProfileMenu" aria-expanded="false" data-no-loader>
                        <span class="user-avatar">@if($accountPhoto)<img src="{{ $accountPhoto }}" alt="Profile Photo">@else<i class="{{ $role === 'employer' ? 'fas fa-building' : 'fas fa-user' }}" aria-hidden="true"></i>@endif</span>
                        <span class="user-info"><span class="user-name">{{ $accountName }}</span><span class="user-role">{{ $accountType }}</span></span><i class="fas fa-chevron-down chevron" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu" id="{{ $role }}ProfileMenu">
                        <div class="dropdown-header"><div class="dh-name">{{ $accountName }}</div><div class="dh-role">{{ $accountType }} Account</div></div>
                        <a href="{{ route($profileRoute) }}" class="dropdown-item" data-no-loader><i class="fas fa-user-pen" aria-hidden="true"></i> Profile</a>
                        <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="dropdown-item danger logout-button" data-loading><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="cn-admin-content cn-dashboard-main" data-ajax-region>{{ $slot }}</main>
        <footer class="cn-admin-footer">&copy; {{ date('Y') }} Career Next. All rights reserved.</footer>
    </div>
</div>

<button class="cn-admin-backdrop" type="button" aria-label="Close {{ $roleTitle }} navigation" tabindex="-1" data-role-drawer-close></button>
<aside class="cn-admin-drawer" id="{{ $role }}MobileDrawer" role="dialog" aria-modal="true" aria-label="{{ $roleTitle }} navigation" aria-hidden="true" inert>
    <div class="cn-admin-drawer-header">
        <a href="{{ route($dashboardRoute) }}" class="brand-logo cn-admin-brand cn-admin-drawer-brand"><img class="cn-logo-image cn-logo-image--sidebar" src="{{ asset('assets/images/career_next.png') }}" alt=""><span>Career Next</span></a>
        <div class="theme-toggle-wrapper"><button class="theme-toggle" type="button" aria-label="Choose color theme"></button></div>
        <button class="cn-admin-drawer-close" type="button" aria-label="Close {{ $roleTitle }} navigation" data-role-drawer-close><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="cn-admin-workspace-label">{{ $roleTitle }} workspace</div>
    <x-dashboard-sidebar :role="$role" />
    <div class="cn-admin-drawer-account">
        <div class="cn-admin-user-summary"><span class="cn-admin-user-avatar">@if($accountPhoto)<img src="{{ $accountPhoto }}" alt="">@else<i class="{{ $role === 'employer' ? 'fas fa-building' : 'fas fa-user' }}" aria-hidden="true"></i>@endif</span><span class="cn-admin-user-copy"><strong>{{ $accountName }}</strong><small>{{ $accountType }}</small></span></div>
        <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="cn-admin-drawer-logout" data-loading><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout</button></form>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById('profileDropdown');
    const trigger = document.getElementById('profileTrigger');
    if (!dropdown || !trigger) return;
    trigger.addEventListener('click', function(event) {
        event.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', function(event) {
        if (!dropdown.contains(event.target)) { dropdown.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false'); }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && dropdown.classList.contains('open')) { dropdown.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false'); trigger.focus(); }
    });
});
</script>
