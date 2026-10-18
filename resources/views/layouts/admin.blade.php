<!DOCTYPE html>
<html lang="en">

<head>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
            <a href="{{ route('admin.index') }}" class="brand-logo cn-admin-brand">
                <img src="{{ asset('images/CareerNext.png') }}" alt="">
                <span>Career Next</span>
            </a>
            <div class="cn-admin-workspace-label">Admin workspace</div>
            @include('components.admin-navigation-links')
        </aside>

        <div class="cn-admin-main-column">
            <header class="cn-admin-header">
                <button class="cn-admin-menu-toggle" type="button" aria-label="Open Admin navigation" aria-controls="adminMobileDrawer" aria-expanded="false">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>
                <a href="{{ route('admin.index') }}" class="brand-logo cn-admin-mobile-brand">
                    <img src="{{ asset('images/CareerNext.png') }}" alt="">
                    <span>Career Next</span>
                </a>
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
                        <button type="submit" class="btn-logout">
                            <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="cn-admin-content">
                @yield('content')
            </main>

            <footer class="cn-admin-footer">&copy; {{ date('Y') }} Career Next. All rights reserved.</footer>
        </div>
    </div>

    <button class="cn-admin-backdrop" type="button" aria-label="Close Admin navigation" tabindex="-1" data-admin-drawer-close></button>
    <aside class="cn-admin-drawer" id="adminMobileDrawer" role="dialog" aria-modal="true" aria-labelledby="adminMobileDrawerTitle" aria-hidden="true" inert>
        <div class="cn-admin-drawer-header">
            <a href="{{ route('admin.index') }}" class="brand-logo cn-admin-brand cn-admin-drawer-brand">
                <img src="{{ asset('images/CareerNext.png') }}" alt="">
                <span id="adminMobileDrawerTitle">Career Next</span>
            </a>
            <button class="cn-admin-drawer-close" type="button" aria-label="Close Admin navigation" data-admin-drawer-close>
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
                <button type="submit" class="cn-admin-drawer-logout">
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
