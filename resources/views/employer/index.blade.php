@extends('layouts.employer')

@section('title', 'Employer Dashboard')

@section('content')
<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome, {{ session('UserName') }}!</h1>
            <p>Manage your hiring activity, job listings, and candidate applications from one place.</p>
            <div class="role-badge">
                <i class="fas fa-building"></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration"><i class="fas fa-briefcase" aria-hidden="true"></i></div>
    </div>


    @include('components.dashboard-analytics', ['role' => 'employer', 'analytics' => $analytics])


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
                        Keep your company profile complete so candidates can trust your listings.
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
                profile details completed
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
                'company_name' => 'fa-building',
                'email' => 'fa-envelope',
                'mobile' => 'fa-phone',
                'contact_person' => 'fa-user-tie',
                'area_work' => 'fa-briefcase',
                'city' => 'fa-city',
                'address' => 'fa-location-dot',
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
                href="{{ route('employer.profile.index') }}"
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
                <strong>Your company profile is complete!</strong>

                <span>
                    You have provided all the essential profile information.
                </span>
            </div>

        </div>

        @endif

    </div>
</div><!-- /main-content -->
@endsection
