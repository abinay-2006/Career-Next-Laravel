    <!DOCTYPE html>
    <html lang="en">

    <head>
    <script src="{{ asset('assets/js/theme-boot.js') }}"></script>

        <meta charset="UTF-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>@yield('title')</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

        <link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}">

        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">

        <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/register.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
        @vite('resources/js/app.js')
        @stack('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-mode.css') }}">
        <script src="{{ asset('assets/js/theme-manager.js') }}"></script>

    </head>

    <body>
    <div class="theme-toggle-wrapper" style="position: absolute; top: 10px; right: 10px;">
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

        <div id="alert-container" class="cn-alert-container" aria-label="Notifications">
            @foreach (['success', 'error', 'warning', 'info'] as $alertType)
                @if(session()->has($alertType) && !($alertType === 'error' && $errors->any()))
                    <x-alert :type="$alertType" :message="session($alertType)" :duration="$alertType === 'success' ? 4500 : ($alertType === 'info' ? 5000 : null)" />
                @endif
            @endforeach
            @if($errors->any())
                <x-alert type="error" title="Please review the form" :message="$errors->first()" />
            @endif
        </div>

        @yield('content')

        <script src="{{ asset('assets/js/alerts.js') }}"></script>

        @yield('scripts')

        <script src="{{ asset('assets/js/login.js') }}"></script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    </body>

    </html>
