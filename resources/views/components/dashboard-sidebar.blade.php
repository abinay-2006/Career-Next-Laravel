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
            ['Manage Jobs', 'employer.jobs.index', 'fas fa-briefcase'],
            ['Walk-in Interviews', 'employer.walkins.index', 'fas fa-calendar'],
            ['Applications', 'employer.applications.index', 'fas fa-user-check'],
            ['Company Profile', 'employer.profile.index', 'fas fa-building'],
            ['Feedback', 'employer.feedback.create', 'fas fa-comment'],
            ['Career News', 'news.index', 'fas fa-newspaper'],
        ],
        'jobseeker' => [
            ['Dashboard', 'jobseeker.index', 'fas fa-house'],
            ['Browse Jobs', 'jobseeker.jobs', 'fas fa-briefcase'],
            ['Compare Jobs', 'jobseeker.compare.index', 'fas fa-scale-balanced'],
            ['Walk-in Interviews', 'jobseeker.walkins', 'fas fa-calendar'],
            ['Profile', 'jobseeker.profile.index', 'fas fa-user'],
            ['Career Preferences', 'jobseeker.preferences.edit', 'fas fa-sliders'],
            ['Education & Documents', 'jobseeker.education', 'fas fa-graduation-cap'],
            ['Resume Builder', 'jobseeker.resumes.index', 'fas fa-file-lines'],
            ['My Resumes', 'jobseeker.my-resumes.index', 'fas fa-folder-open'],
            ['My Applications', 'jobseeker.applications.index', 'fas fa-clock-rotate-left'],
            ['Follow-up Coach', 'jobseeker.followup.index', 'fas fa-envelope-circle-check'],
            ['Feedback', 'jobseeker.feedback.create', 'fas fa-comment'],
            ['Career News', 'news.index', 'fas fa-newspaper'],
        ],
    ];
@endphp
<nav class="cn-admin-nav" aria-label="{{ ucfirst($role) }} pages" data-no-loader>
        @foreach ($menus[$role] as [$label, $routeName, $icon])
            @php
                $activePattern = match ($routeName) {
                    'admin.dashboard' => ['admin.dashboard', 'admin.index'],
                    'employer.jobs.index' => 'employer.jobs.*',
                    'employer.applications.index' => 'employer.applications.*',
                    'employer.profile.index' => 'employer.profile.*',
                    'employer.walkins.index' => 'employer.walkins.*',
                    'employer.feedback.create' => 'employer.feedback.*',
                    'jobseeker.jobs' => 'jobseeker.jobs*',
                    'jobseeker.compare.index' => 'jobseeker.compare.*',
                    'jobseeker.saved-jobs.index' => 'jobseeker.saved-jobs.*',
                    'jobseeker.profile.index' => 'jobseeker.profile.*',
                    'jobseeker.education' => 'jobseeker.education*',
                    'jobseeker.resumes.index' => 'jobseeker.resumes.*',
                    'jobseeker.my-resumes.index' => 'jobseeker.my-resumes.*',
                    'jobseeker.applications.index' => 'jobseeker.applications.*',
                    'jobseeker.feedback.create' => 'jobseeker.feedback.*',
                    'news.index' => 'news.*',
                    default => $routeName,
                };
            @endphp
            <a class="cn-admin-nav-link {{ request()->routeIs(...(array) $activePattern) ? 'is-active' : '' }}"
               href="{{ route($routeName) }}" @if(request()->routeIs(...(array) $activePattern)) aria-current="page" @endif>
                <i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                @if ($role === 'jobseeker' && $routeName === 'jobseeker.compare.index' && ($compareCount = \App\Support\JobComparisonState::count()) > 0)
                    <span class="cn-cmp-nav-count">{{ $compareCount }}</span>
                @endif
            </a>
        @endforeach
</nav>
