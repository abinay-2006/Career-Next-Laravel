<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOB PORTAL – Control Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/jobseeker.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
</head>

<body>
    @php
    $headerJobSeeker = Auth::guard('jobseeker')->user();
    @endphp
    <!-- ══ NAVBAR ══ -->
    <nav class="navbar">
        <a href="{{ route('jobseeker.index') }}" class="navbar-brand brand-logo">
            <img src="{{ asset('images/CareerNext.png') }}" alt="Career Next">
            <span>Career Next</span>
        </a>

        <div class="navbar-user">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-trigger" onclick="toggleDropdown()">

                    <div class="user-avatar">

                        @if($headerJobSeeker && !empty($headerJobSeeker->Photo))
                        <img
                            src="{{ asset('upload/photos/' . $headerJobSeeker->Photo) }}"
                            alt="Profile Photo">
                        @else
                        <i class="fas fa-user"></i>
                        @endif

                    </div>

                    <div class="user-info">
                        <div class="user-name">{{ session('UserName') }}</div>
                        <div class="user-role">{{ session('UserType') }}</div>
                    </div>
                    <i class="fas fa-chevron-down chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <div class="dh-name">{{ session('UserName') }}</div>
                        <div class="dh-role">{{ session('UserType') }} Account</div>
                    </div>
                    <a href="{{ route('jobseeker.profile.index') }}" class="dropdown-item">
                        <i class="fas fa-user-pen"></i> Profile
                    </a>
                    <a href="{{ route('logout') }}" method="POST" class="dropdown-item danger">
                        <i class="fas fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    @yield('content')
    <footer class="footer">
        &copy; <?php echo date('Y'); ?> Job Portal. All rights reserved.
    </footer>
    @yield('scripts')
    <script>
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('open');
        }
        document.addEventListener('click', function(e) {
            const dd = document.getElementById('profileDropdown');
            if (!dd.contains(e.target)) dd.classList.remove('open');
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>