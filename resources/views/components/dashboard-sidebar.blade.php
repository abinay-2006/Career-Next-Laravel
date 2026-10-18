@php
    $menus = [
        'admin' => [
            ['Dashboard', 'admin.dashboard', 'fas fa-chart-line'],
            ['Manage Users', 'admin.manageUsers', 'fas fa-users'],
            ['Manage Jobs', 'admin.manage.jobs', 'fas fa-briefcase'],
            ['Manage Documents', 'admin.manage.documents', 'fas fa-file-lines'],
            ['News', 'admin.news.index', 'fas fa-newspaper'],
            ['Feedback', 'admin.feedback.index', 'fas fa-comment'],
            ['Activity Logs', 'admin.logs.index', 'fas fa-clipboard-list'],
        ],
        'employer' => [
            ['Dashboard', 'employer.dashboard', 'fas fa-house'],
            ['Company Profile', 'employer.profile.index', 'fas fa-building'],
            ['Manage Jobs', 'employer.jobs.index', 'fas fa-briefcase'],
            ['Applications', 'employer.applications.index', 'fas fa-user-check'],
            ['Walk-in Interviews', 'employer.walkins.index', 'fas fa-calendar'],
            ['Career News', 'news.index', 'fas fa-newspaper'],
        ],
        'jobseeker' => [
            ['Dashboard', 'jobseeker.index', 'fas fa-house'],
            ['Browse Jobs', 'jobseeker.jobs', 'fas fa-magnifying-glass'],
            ['Walk-in Interviews', 'jobseeker.walkins', 'fas fa-calendar'],
            ['Profile', 'jobseeker.profile.index', 'fas fa-user'],
            ['Education & Documents', 'jobseeker.education', 'fas fa-graduation-cap'],
            ['My Applications', 'jobseeker.applications.index', 'fas fa-clock-rotate-left'],
            ['Feedback', 'jobseeker.feedback.create', 'fas fa-comment'],
            ['Career News', 'news.index', 'fas fa-newspaper'],
        ],
    ];
@endphp
<aside class="cn-sidebar" id="roleSidebar" aria-label="{{ ucfirst($role) }} navigation">
    <div class="cn-sidebar-heading">{{ ucfirst($role) }} workspace</div>
    <nav class="cn-sidebar-nav">
        @foreach ($menus[$role] as [$label, $routeName, $icon])
            @php
                $activePattern = match ($routeName) {
                    'admin.dashboard' => ['admin.dashboard', 'admin.index'],
                    'employer.jobs.index' => 'employer.jobs.*',
                    'employer.applications.index' => 'employer.applications.*',
                    'employer.profile.index' => 'employer.profile.*',
                    'employer.walkins.index' => 'employer.walkins.*',
                    'jobseeker.jobs' => 'jobseeker.jobs*',
                    'jobseeker.profile.index' => 'jobseeker.profile.*',
                    'jobseeker.education' => 'jobseeker.education*',
                    'jobseeker.applications.index' => 'jobseeker.applications.*',
                    'jobseeker.feedback.create' => 'jobseeker.feedback.*',
                    default => $routeName,
                };
            @endphp
            <a class="cn-sidebar-link {{ request()->routeIs(...(array) $activePattern) ? 'is-active' : '' }}"
               href="{{ route($routeName) }}" @if(request()->routeIs(...(array) $activePattern)) aria-current="page" @endif>
                <i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</aside>
