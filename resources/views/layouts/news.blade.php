<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Career News') | Career Next</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/news.css') }}">
</head>
<body class="cn-news-public">
    <header class="cn-news-public-header">
        <a href="{{ url('/') }}" class="brand-logo"><img src="{{ asset('images/CareerNext.png') }}" alt=""><span>Career Next</span></a>
        <nav aria-label="Main navigation"><a href="{{ route('news.index') }}" aria-current="page">Career News</a><a href="{{ url('/') }}">Home</a><a href="{{ route('login') }}">Sign in</a></nav>
    </header>
    <main class="cn-news-main">@yield('content')</main>
    <footer class="cn-news-footer">&copy; {{ date('Y') }} Career Next. All rights reserved.</footer>
</body>
</html>
