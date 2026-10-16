<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Career Next') | Career Next</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/jobseeker.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
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
    @php
    $headerJobSeeker = Auth::guard('jobseeker')->user();
    @endphp

    <!-- ══ NAVBAR ══ -->
    <nav class="navbar">
        <button class="cn-menu-toggle" type="button" aria-label="Open navigation" aria-controls="roleSidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>

        <a href="{{ route('jobseeker.index') }}" class="navbar-brand brand-logo">
            <img src="{{ asset('images/CareerNext.png') }}" alt="Career Next">
            <span>Career Next</span>
        </a>

        <div class="navbar-user">

            <div class="profile-dropdown" id="profileDropdown">

                <button type="button" class="profile-trigger" id="profileTrigger" aria-controls="jobseekerProfileMenu" aria-expanded="false" onclick="toggleDropdown()">

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

                        <div class="user-name">
                            {{ session('UserName') }}
                        </div>

                        <div class="user-role">
                            {{ session('UserType') }}
                        </div>

                    </div>

                    <i class="fas fa-chevron-down chevron"></i>

                </button>


                <div class="dropdown-menu" id="jobseekerProfileMenu">

                    <div class="dropdown-header">

                        <div class="dh-name">
                            {{ session('UserName') }}
                        </div>

                        <div class="dh-role">
                            {{ session('UserType') }} Account
                        </div>

                    </div>


                    <a href="{{ route('jobseeker.profile.index') }}"
                        class="dropdown-item">

                        <i class="fas fa-user-pen"></i>
                        Profile

                    </a>


                    <!-- Logout -->
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf

                        <button type="submit"
                            class="dropdown-item danger logout-button">

                            <i class="fas fa-right-from-bracket"></i>
                            Logout

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </nav>



    <div class="cn-dashboard-layout">
        <div class="cn-sidebar-backdrop" data-sidebar-close></div>
        <x-dashboard-sidebar role="jobseeker" />
        <main class="cn-dashboard-main">@yield('content')</main>
    </div>
    <footer class="footer">
        &copy; <?php echo date('Y'); ?> Job Portal. All rights reserved.
    </footer>
    @yield('scripts')
    <script src="{{ asset('assets/js/dashboard-sidebar.js') }}"></script>
    <script src="{{ asset('assets/js/alerts.js') }}"></script>
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.getElementById('profileTrigger');
            const isOpen = dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', String(isOpen));
        }
        document.addEventListener('click', function(e) {
            const dd = document.getElementById('profileDropdown');
            if (!dd.contains(e.target)) {
                dd.classList.remove('open');
                document.getElementById('profileTrigger').setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('profileDropdown').classList.remove('open');
                const trigger = document.getElementById('profileTrigger');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
