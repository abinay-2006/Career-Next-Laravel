<!DOCTYPE html>
<html lang="en">

<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JOB PORTAL – Control Panel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/employer.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/employer-profile.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/account-ui.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/news.css') }}">
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
  @php
  $headerEmployer = Auth::guard('employer')->user();
  @endphp
  <!-- ══ NAVBAR ══ -->

  <nav class="navbar">
    <button class="cn-menu-toggle" type="button" aria-label="Open navigation" aria-controls="roleSidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
    <a href="{{ route('employer.dashboard') }}" class="navbar-brand brand-logo">
      <img src="{{ asset('images/CareerNext.png') }}" alt="Career Next">
      <span>Career Next</span>
    </a>

    <div class="navbar-user">

      <div class="profile-dropdown" id="profileDropdown">

        <!-- Profile Trigger -->
        <button type="button"
          class="profile-trigger"
          id="profileTrigger"
          aria-expanded="false">

          <div class="user-avatar">

            @if($headerEmployer && ($headerPhotoUrl = \App\Support\ProfilePhoto::url($headerEmployer->Photo)))
            <img
              src="{{ $headerPhotoUrl }}"
              alt="Company Logo">
            @else
            <i class="fas fa-building"></i>
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


        <!-- Custom Profile Menu -->
        <div class="employer-dropdown-menu" id="profileMenu">

          <div class="employer-dropdown-header">

            <div class="dh-name">
              {{ session('UserName') }}
            </div>

            <div class="dh-role">
              {{ session('UserType') }} Account
            </div>

          </div>


          <a href="{{ route('employer.profile.index') }}"
            class="employer-dropdown-item">

            <i class="fas fa-user-pen"></i>

            <span>Profile</span>

          </a>


          <!-- Logout -->
          <form action="{{ route('logout') }}" method="POST">
            @csrf

            <button type="submit"
              class="employer-dropdown-item danger">

              <i class="fas fa-right-from-bracket"></i>

              <span>Logout</span>

            </button>
          </form>

        </div>

      </div>

    </div>
  </nav>
  <div class="cn-dashboard-layout">
    <div class="cn-sidebar-backdrop" data-sidebar-close></div>
    <x-dashboard-sidebar role="employer" />
    <main class="cn-dashboard-main">@yield('content')</main>
  </div>
  @yield('scripts')
  <script src="{{ asset('assets/js/dashboard-sidebar.js') }}"></script>
  <script src="{{ asset('assets/js/alerts.js') }}"></script>
  <script src="{{ asset('assets/js/dashboard-analytics.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {

      const dropdown = document.getElementById('profileDropdown');
      const trigger = document.getElementById('profileTrigger');

      if (!dropdown || !trigger) {
        return;
      }

      /*
      |--------------------------------------------------------------------------
      | Toggle dropdown
      |--------------------------------------------------------------------------
      */

      trigger.addEventListener('click', function(event) {

        event.stopPropagation();

        const isOpen = dropdown.classList.contains('open');

        dropdown.classList.toggle('open', !isOpen);

        trigger.setAttribute(
          'aria-expanded',
          !isOpen ? 'true' : 'false'
        );

      });


      /*
      |--------------------------------------------------------------------------
      | Close when clicking outside
      |--------------------------------------------------------------------------
      */

      document.addEventListener('click', function(event) {

        if (!dropdown.contains(event.target)) {

          dropdown.classList.remove('open');

          trigger.setAttribute(
            'aria-expanded',
            'false'
          );

        }

      });


      /*
      |--------------------------------------------------------------------------
      | Close with Escape
      |--------------------------------------------------------------------------
      */

      document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

          dropdown.classList.remove('open');

          trigger.setAttribute(
            'aria-expanded',
            'false'
          );

          trigger.focus();

        }

      });

    });
  </script>
  <footer class="footer">
    &copy; {{ date('Y') }} Career Next. All rights reserved.
  </footer>
</body>

</html>
