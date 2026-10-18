@php
    $adminNavigationItems = [
        ['Dashboard', 'admin.dashboard', ['admin.dashboard', 'admin.index'], 'fas fa-chart-line'],
        ['Manage Users', 'admin.manageUsers', ['admin.manageUsers'], 'fas fa-users'],
        ['Manage Jobs', 'admin.manage.jobs', ['admin.manage.jobs'], 'fas fa-briefcase'],
        ['Manage Documents', 'admin.manage.documents', ['admin.manage.documents'], 'fas fa-file-lines'],
    ];
@endphp

<nav class="cn-admin-nav" aria-label="Admin pages">
    @foreach ($adminNavigationItems as [$label, $routeName, $activeRoutes, $icon])
        <a class="cn-admin-nav-link {{ request()->routeIs(...$activeRoutes) ? 'is-active' : '' }}"
           href="{{ route($routeName) }}"
           @if(request()->routeIs(...$activeRoutes)) aria-current="page" @endif>
            <i class="{{ $icon }}" aria-hidden="true"></i>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
