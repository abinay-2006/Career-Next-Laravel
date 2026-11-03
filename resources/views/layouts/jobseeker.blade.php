<!DOCTYPE html>
<html lang="en">
<head>
    <script src="{{ asset('assets/js/theme-boot.js') }}"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Career Next') | Career Next</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/jobseeker.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-navigation.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/account-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/news.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/resume-builder.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/my-resume.css') }}">
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-mode.css') }}">
    @yield('styles')
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
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
    <x-role-dashboard-shell role="jobseeker">@yield('content')</x-role-dashboard-shell>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/alerts.js') }}"></script>
    @yield('scripts')
    <script src="{{ asset('assets/js/admin-navigation.js') }}"></script>
    <script src="{{ asset('assets/js/dashboard-analytics.js') }}"></script>
</body>
</html>
