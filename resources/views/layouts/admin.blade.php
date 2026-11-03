<!DOCTYPE html>
<html lang="en">

<head>
    <script src="{{ asset('assets/js/theme-boot.js') }}"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') | Career Next</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-navigation.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('assets/js/theme-manager.js') }}"></script>
</head>

<body>
    <div id="alert-container" class="cn-alert-container" aria-label="Notifications">
        @foreach (['success', 'error', 'warning', 'info'] as $alertType)
            @if(session()->has($alertType) && !($alertType === 'error' && $errors->any()))
                <x-alert :type="$alertType" :message="session($alertType)" :duration="$alertType === 'success' ? 4500 : ($alertType === 'info' ? 5000 : null)" />
            @endif
        @endforeach
        @if($errors->any())<x-alert type="error" title="Please review the form" :message="$errors->first()" />@endif
    </div>

    <div class="cn-admin-shell">
        <aside class="cn-admin-sidebar" aria-label="Admin navigation">
            <a href="{{ route('admin.dashboard') }}" class="brand-logo cn-admin-brand">
                <img class="cn-logo-image cn-logo-image--sidebar" src="{{ asset('assets/images/career_next.png') }}" alt="">
                <span style="color: #ffff;">Career Next</span>
            </a>
            <div class="cn-admin-workspace-label">Admin workspace</div>
            @include('components.admin-navigation-links')
        </aside>

        <div class="cn-admin-main-column">
            <header class="cn-admin-header">
                <button class="cn-admin-menu-toggle" type="button" aria-label="Open Admin navigation" aria-controls="adminMobileDrawer" aria-expanded="false" data-role-drawer-toggle><i class="fas fa-bars" aria-hidden="true"></i></button>
                <a href="{{ route('admin.dashboard') }}" class="brand-logo cn-admin-brand cn-admin-mobile-brand"><img class="cn-logo-image cn-logo-image--mobile" src="{{ asset('assets/images/career_next.png') }}" alt=""><span>Career Next</span></a>
                <div class="cn-admin-theme-controls">
                    <button class="theme-toggle" type="button" aria-label="Choose color theme">
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="cn-admin-header-account">
                    <div class="cn-admin-user-summary">
                        <span class="cn-admin-user-avatar"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                        <span class="cn-admin-user-copy">
                            <strong>{{ session('Name') }}</strong>
                            <small>{{ session('UserType') }}</small>
                        </span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-logout" data-loading>
                            <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="cn-admin-content" data-ajax-region>
                @yield('content')
            </main>

            <footer class="cn-admin-footer">&copy; {{ date('Y') }} Career Next. All rights reserved.</footer>
        </div>
    </div>

    <button class="cn-admin-backdrop" type="button" aria-label="Close Admin navigation" tabindex="-1" data-admin-drawer-close data-role-drawer-close></button>
    <aside class="cn-admin-drawer" id="adminMobileDrawer" role="dialog" aria-modal="true" aria-labelledby="adminMobileDrawerTitle" aria-hidden="true" inert>
        <div class="cn-admin-drawer-header">
            <a href="{{ route('admin.dashboard') }}" class="brand-logo cn-admin-brand cn-admin-drawer-brand">
                <img class="cn-logo-image cn-logo-image--sidebar" src="{{ asset('assets/images/career_next.png') }}" alt="">
                <span id="adminMobileDrawerTitle">Career Next</span>
            </a>
            <button type="button" class="cn-admin-theme-toggle theme-toggle" aria-label="Choose color theme">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
            <button class="cn-admin-drawer-close" type="button" aria-label="Close Admin navigation" data-admin-drawer-close data-role-drawer-close>
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="cn-admin-workspace-label">Admin workspace</div>
        @include('components.admin-navigation-links')
        <div class="cn-admin-drawer-account">
            <div class="cn-admin-user-summary">
                <span class="cn-admin-user-avatar"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                <span class="cn-admin-user-copy">
                    <strong>{{ session('Name') }}</strong>
                    <small>{{ session('UserType') }}</small>
                </span>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="cn-admin-drawer-logout" data-loading>
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/alerts.js') }}"></script>
    @yield('scripts')
    <script src="{{ asset('assets/js/admin-navigation.js') }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
