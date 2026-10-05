<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Next – Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/brand.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
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
    <!-- ══════════ NAVBAR ══════════ -->
    <nav class="navbar">
        <a href="{{ route('admin.index') }}" class="navbar-brand brand-logo">
            <img src="../images/CareerNext.png" alt="Career Next" onerror="this.style.display='none'">
            <span style="color: white;">Career Next</span>
        </a>
        <div class="navbar-user">
            <div class="user-badge">
                <div class="user-avatar"><i class="fas fa-shield-halved"></i></div>
                <div class="user-info">
                    <div class="user-name">{{ session('Name') }}</div>
                    <div class="user-role">{{ session('UserType') }}</div>
                </div>
            </div>
            
            <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                @csrf

                <button type="submit" class="btn-logout">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </button>
            </form>
        </div>
    </nav>


    @yield('content')
    <script src="{{ asset('assets/js/alerts.js') }}"></script>
    @yield('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
<!-- ══ FOOTER ══ -->
<footer class="footer">
    &copy; <?php echo date('Y'); ?> Career Next. All rights reserved.
</footer>

</html>
