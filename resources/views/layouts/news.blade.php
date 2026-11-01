<!doctype html>
<html lang="en">
<head>
    <script src="{{ asset('assets/js/theme-boot.js') }}"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Career News') | Career Next</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/news.css') }}">
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-mode.css') }}">
    <script src="{{ asset('assets/js/theme-manager.js') }}"></script>
</head>
<body class="cn-news-public">
    <header class="cn-news-public-header">
        <a href="{{ url('/') }}" class="brand-logo"><img src="{{ asset('images/CareerNext.png') }}" alt=""><span>Career Next</span></a>
        <nav aria-label="Main navigation"><a href="{{ route('news.index') }}" aria-current="page">Career News</a><a href="{{ url('/') }}">Home</a><button class="theme-toggle" type="button" aria-label="Choose color theme"><svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg><svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></button><a href="{{ route('login') }}">Sign in</a></nav>
    </header>
    <main class="cn-news-main" data-ajax-region>@yield('content')</main>
    <footer class="cn-news-footer">&copy; {{ date('Y') }} Career Next. All rights reserved.</footer>
</body>
</html>
