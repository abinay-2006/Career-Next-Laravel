@extends('layouts.jobseeker')

@section('title','Jobseeker Portal')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome to Control Panel 👋</h1>
            <p>Manage your job search and career activity from one place.<br>
                Use the quick actions below to navigate.</p>
            <div class="role-badge">
                <i class="fas "></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration">🗂️</div>
    </div>


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
                    class="missing-profile-item">

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
                class="complete-profile-btn">

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


    <!-- Quick Actions -->
    <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>

    <div class="cards-grid">

        <a href="{{ route('jobseeker.education') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-graduation-cap"></i></div>
            <div>
                <div class="card-title">Education</div>
                <div class="card-desc">Add or update your educational qualifications and certificates.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Update</div>
        </a>

        <a href="{{ route('jobseeker.jobs') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-magnifying-glass"></i></div>
            <div>
                <div class="card-title">Search Job</div>
                <div class="card-desc">Browse and apply for available job openings on the portal.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Explore</div>
        </a>

        <a href="{{ route('jobseeker.walkins') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div>
                <div class="card-title">Walk-in</div>
                <div class="card-desc">View and register for upcoming walk-in interview drives.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> View</div>
        </a>
    </div>
</div><!-- /main-content -->
@endsection