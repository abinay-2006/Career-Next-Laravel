@php
    $menus = [
        'admin' => [
            ['Dashboard', 'admin.dashboard', 'fas fa-chart-line'],
            ['Manage Users', 'admin.manageUsers', 'fas fa-users'],
            ['Manage Jobs', 'admin.manage.jobs', 'fas fa-briefcase'],
            ['Manage Documents', 'admin.manage.documents', 'fas fa-file-lines'],
        ],
        'employer' => [
            ['Dashboard', 'employer.dashboard', 'fas fa-house'],
            ['Company Profile', 'employer.profile.index', 'fas fa-building'],
            ['Manage Jobs', 'employer.jobs.index', 'fas fa-briefcase'],
            ['Applications', 'employer.applications.index', 'fas fa-user-check'],
            ['Walk-in Interviews', 'employer.walkins.index', 'fas fa-calendar'],
        ],
        'jobseeker' => [
            ['Dashboard', 'jobseeker.index', 'fas fa-house'],
            ['Browse Jobs', 'jobseeker.jobs', 'fas fa-magnifying-glass'],
            ['Walk-in Interviews', 'jobseeker.walkins', 'fas fa-calendar'],
            ['Profile', 'jobseeker.profile.index', 'fas fa-user'],
            ['Education & Documents', 'jobseeker.education', 'fas fa-graduation-cap'],
        ],
    ];
@endphp
<aside class="cn-sidebar" id="roleSidebar" aria-label="{{ ucfirst($role) }} navigation">
    <div class="cn-sidebar-heading">{{ ucfirst($role) }} workspace</div>
    <nav class="cn-sidebar-nav">
        @foreach ($menus[$role] as [$label, $routeName, $icon])
            @php
                $activePattern = match ($routeName) {
                    'employer.jobs.index' => 'employer.jobs.*',
                    'employer.applications.index' => 'employer.applications.*',
                    'employer.profile.index' => 'employer.profile.*',
                    'employer.walkins.index' => 'employer.walkins.*',
                    'jobseeker.jobs' => 'jobseeker.jobs*',
                    'jobseeker.profile.index' => 'jobseeker.profile.*',
                    'jobseeker.education' => 'jobseeker.education*',
                    default => $routeName,
                };
            @endphp
            <a class="cn-sidebar-link {{ request()->routeIs($activePattern) ? 'is-active' : '' }}"
               href="{{ route($routeName) }}" @if(request()->routeIs($activePattern)) aria-current="page" @endif>
                <i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
            </a>
        @endforeach
        <form action="{{ route('logout') }}" method="POST" class="cn-sidebar-logout">
            @csrf
            <button type="submit" class="cn-sidebar-link"><i class="fas fa-right-from-bracket" aria-hidden="true"></i><span>Logout</span></button>
        </form>
    </nav>
</aside>
