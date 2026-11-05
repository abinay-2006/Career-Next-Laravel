@extends('layouts.jobseeker')

@section('title','Jobseeker Portal')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome, {{ session('UserName') }}!</h1>
            <p>Manage your job search and career activity from one place.</p>
            <div class="role-badge">
                <i class="fas fa-user"></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration"><i class="fas fa-compass" aria-hidden="true"></i></div>
    </div>

    @include('components.dashboard-analytics', ['role' => 'jobseeker', 'analytics' => $analytics])


    <!-- PROFILE COMPLETION -->
    <div class="profile-completion-card">

        <div class="profile-completion-header">

            <div class="profile-completion-title">

                <div class="profile-completion-icon">
                    <i class="fas fa-user-check"></i>
                </div>

                <div>
                    <h2>Profile Completion</h2>

                    <p>
                        Keep your profile updated to improve your job application experience.
                    </p>
                </div>

            </div>

            <div class="profile-completion-percentage">
                {{ $profileCompletion }}%
            </div>

        </div>


        <!-- Progress Bar -->
        <div class="profile-progress">

            <div
                class="profile-progress-fill"
                style="width: {{ $profileCompletion }}%;">
            </div>

        </div>


        <!-- Completion Status -->
        <div class="profile-completion-status">

            <span>
                <strong>{{ $completedItems }}</strong>
                of
                <strong>{{ $totalItems }}</strong>
                profile sections completed
            </span>

            @if($profileCompletion >= 100)

            <span class="completion-complete">
                <i class="fas fa-circle-check"></i>
                Profile complete
            </span>

            @else

            <span class="completion-incomplete">
                <i class="fas fa-circle-exclamation"></i>
                {{ count($missingProfileItems) }} remaining
            </span>

            @endif

        </div>


        <!-- Missing Information -->
        @if(count($missingProfileItems) > 0)

        <div class="missing-profile-section">

            <div class="missing-profile-title">
                <i class="fas fa-list-check"></i>
                Complete your profile
            </div>

            <div class="missing-profile-list">

                @php
                $profileSectionIcons = [
                'name' => 'fa-user',
                'email' => 'fa-envelope',
                'mobile' => 'fa-phone',
                'city' => 'fa-city',
                'address' => 'fa-location-dot',
                'gender' => 'fa-venus-mars',
                'birth_date' => 'fa-cake-candles',
                'education' => 'fa-graduation-cap',
                'resume' => 'fa-file-lines',
                'photo' => 'fa-camera',
                ];
                @endphp

                @foreach($missingProfileItems as $item)

                <a href="{{ route($item['route']) }}"
                    class="btn complete-profile-btn">

                    <span>
                        <i class="fas {{ $profileSectionIcons[$item['key']] ?? 'fa-circle-exclamation' }}"></i>

                        {{ $item['label'] }}
                    </span>

                    <i class="fas fa-arrow-right"></i>

                </a>

                @endforeach

            </div>

            <a
                href="{{ route('jobseeker.profile.index') }}"
                class="btn complete-profile-btn">

                <i class="fas fa-pen"></i>
                Complete Profile

                <i class="fas fa-arrow-right"></i>

            </a>

        </div>

        @else

        <div class="profile-complete-message">

            <i class="fas fa-circle-check"></i>

            <div>
                <strong>Your profile is complete!</strong>

                <span>
                    You have provided all the essential profile information.
                </span>
            </div>

        </div>

        @endif

    </div>

    <div class="cn-recommended-jobs-card profile-completion-card">
        <div class="cn-recommended-jobs-header">
            <h2 class="cn-recommended-jobs-title">
                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                Recommended Jobs
            </h2>
            <a href="{{ route('jobseeker.preferences.edit') }}" class="cn-recommended-jobs-edit">
                <i class="fas fa-sliders" aria-hidden="true"></i>
                Edit Preferences
            </a>
        </div>

        @if(!empty($recommendedJobs))
            <p class="cn-recommended-jobs-note">
                Matched against your career preferences. This is separate from the
                <strong>Skill Match</strong> score you see on Browse Jobs.
            </p>
            <div class="cn-recommended-jobs-grid">
                @foreach($recommendedJobs as $recommended)
                    <article class="cn-recommended-job">
                        <div class="cn-recommended-job-top">
                            <h3 class="cn-recommended-job-title">{{ $recommended['job']->JobTitle }}</h3>
                            <span class="cn-recommended-job-match" title="Preference match based on your selected job titles">
                                {{ $recommended['percentage'] }}%
                            </span>
                        </div>
                        <p class="cn-recommended-job-company">
                            <i class="fas fa-building" aria-hidden="true"></i>
                            {{ $recommended['job']->CompanyName }}
                        </p>
                        @if(!empty($recommended['matched_titles']))
                            <p class="cn-recommended-job-matched">
                                Matches: {{ implode(', ', $recommended['matched_titles']) }}
                            </p>
                        @endif
                        @if(!empty($recommended['matched_classifications']))
                            <p class="cn-recommended-job-matched">
                                Also matches: {{ implode(', ', $recommended['matched_classifications']) }}
                            </p>
                        @endif
                        <a href="{{ route('jobseeker.jobs.show', $recommended['job']->JobId) }}"
                           class="btn complete-profile-btn cn-recommended-job-link">
                            View job <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                @endforeach
            </div>
        @else
            @if(!empty($careerPreferences) && $careerPreferences->hasAnyPreference())
                <p class="cn-recommended-jobs-empty">
                    No approved jobs match your preferences right now — matching jobs will appear
                    here as they are posted.
                </p>
            @else
                <p class="cn-recommended-jobs-empty">
                    Tell us what kind of job you're looking for to receive more relevant recommendations.
                </p>
                <a href="{{ route('jobseeker.preferences.edit') }}" class="btn complete-profile-btn">
                    <i class="fas fa-sliders" aria-hidden="true"></i>
                    Set Career Preferences
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            @endif
        @endif
    </div>
</div><!-- /main-content -->
@endsection
