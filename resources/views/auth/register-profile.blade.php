@extends('layouts.auth')

@section('title', 'Profile Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/register.css') }}">
@endpush

@section('content')

<div class="register-page">

    <div class="register-container">


        <!-- =====================================================
             HEADER
             ===================================================== -->

        <div class="register-header">

            <a href="{{ route('register.index') }}" class="register-logo">

                <span class="logo-icon">
                    <img
                        src="{{ asset('images/CareerNext.png') }}"
                        alt="Career Next Logo"
                        width="42"
                        height="42">
                </span>

                <span>Career Next</span>

            </a>

        </div>


        <!-- =====================================================
             PROGRESS
             ===================================================== -->

        <div class="registration-progress">

            <!-- Step 1 -->

            <div class="progress-step completed">

                <div class="step-number">

                    <i class="fas fa-check"></i>

                </div>

                <span>Account</span>

            </div>


            <div class="progress-line active"></div>


            <!-- Step 2 -->

            <div class="progress-step completed">

                <div class="step-number">

                    <i class="fas fa-check"></i>

                </div>

                <span>Details</span>

            </div>


            <div class="progress-line active"></div>


            <!-- Step 3 -->

            <div class="progress-step active">

                <div class="step-number">
                    3
                </div>

                <span>Profile</span>

            </div>


            <div class="progress-line"></div>


            <!-- Step 4 -->

            <div class="progress-step">

                <div class="step-number">
                    4
                </div>

                <span>Review</span>

            </div>


            <div class="progress-line"></div>


            <!-- Step 5 -->

            <div class="progress-step">

                <div class="step-number">
                    5
                </div>

                <span>Verify</span>

            </div>

        </div>


        <!-- =====================================================
             CARD
             ===================================================== -->

        <div class="registration-card">


            <!-- Header -->

            <div class="card-header">

                <div class="step-badge">

                    @if($accountType === 'JobSeeker')

                    <i class="fas fa-user"></i>

                    @else

                    <i class="fas fa-building"></i>

                    @endif

                </div>


                <div>

                    @if($accountType === 'JobSeeker')

                    <h1>
                        Personal details
                    </h1>

                    <p>
                        Tell us a little more about yourself.
                    </p>

                    @else

                    <h1>
                        Company details
                    </h1>

                    <p>
                        Tell us a little more about your company.
                    </p>

                    @endif

                </div>

            </div>


            <!-- =================================================
                 ACCOUNT SUMMARY
                 ================================================= -->

            <div class="account-type-display">

                @if($accountType === 'JobSeeker')

                <i class="fas fa-user-tie"></i>

                <div>

                    <strong>
                        {{ session('registration.name') }}
                    </strong>

                    <span>
                        {{ session('registration.email') }}
                    </span>

                </div>

                @else

                <i class="fas fa-building"></i>

                <div>

                    <strong>
                        {{ session('registration.name') }}
                    </strong>

                    <span>
                        {{ session('registration.email') }}
                    </span>

                </div>

                @endif

                <a href="{{ route('register.details') }}">
                    Edit
                </a>

            </div>


            <!-- =================================================
                 VALIDATION ERRORS
                 ================================================= -->

            @if($errors->any())

            <div class="registration-alert">

                <i class="fas fa-circle-exclamation"></i>

                <div>

                    <strong>
                        Please check the following:
                    </strong>

                    <ul>

                        @foreach($errors->all() as $error)

                        <li>
                            {{ $error }}
                        </li>

                        @endforeach

                    </ul>

                </div>

            </div>

            @endif


            <!-- =================================================
                 FORM
                 ================================================= -->

            <form
                method="POST"
                action="{{ route('register.profile.store') }}"
                id="profile-form">

                @csrf


                @if($accountType === 'JobSeeker')

                <!-- =========================================
                         JOB SEEKER
                         ========================================= -->


                <!-- Address -->

                <div class="form-group">

                    <label for="address">

                        Address

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-location-dot"></i>

                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="{{ old('address', $profile['address'] ?? '') }}"
                            placeholder="Enter your address"
                            autocomplete="street-address"
                            required>

                    </div>

                    @error('address')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- City -->

                <div class="form-group">

                    <label for="city">

                        City

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-city"></i>

                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="{{ old('city', $profile['city'] ?? '') }}"
                            placeholder="Enter your city"
                            autocomplete="address-level2"
                            required>

                    </div>

                    @error('city')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Gender -->

                <div class="form-group">

                    <label for="gender">

                        Gender

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-venus-mars"></i>

                        <select
                            id="gender"
                            name="gender"
                            required>

                            <option value="">
                                Select gender
                            </option>

                            <option
                                value="Male"
                                @selected(old('gender', $profile['gender'] ?? '' )==='Male' )>
                                Male
                            </option>

                            <option
                                value="Female"
                                @selected(old('gender', $profile['gender'] ?? '' )==='Female' )>
                                Female
                            </option>

                            <option
                                value="Other"
                                @selected(old('gender', $profile['gender'] ?? '' )==='Other' )>
                                Other
                            </option>

                        </select>

                    </div>

                    @error('gender')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Date of Birth -->

                <div class="form-group">

                    <label for="birth_date">

                        Date of Birth

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-calendar"></i>

                        <input
                            type="date"
                            id="birth_date"
                            name="birth_date"
                            value="{{ old('birth_date', $profile['birth_date'] ?? '') }}"
                            max="{{ now()->subDay()->format('Y-m-d') }}"
                            required>

                    </div>

                    @error('birth_date')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                @else

                <!-- =========================================
                         EMPLOYER
                         ========================================= -->


                <!-- Contact Person -->

                <div class="form-group">

                    <label for="contact_person">

                        Contact Person

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-user"></i>

                        <input
                            type="text"
                            id="contact_person"
                            name="contact_person"
                            value="{{ old('contact_person', $profile['contact_person'] ?? '') }}"
                            placeholder="Enter contact person's name"
                            autocomplete="name"
                            required>

                    </div>

                    @error('contact_person')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Address -->

                <div class="form-group">

                    <label for="address">

                        Company Address

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-location-dot"></i>

                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="{{ old('address', $profile['address'] ?? '') }}"
                            placeholder="Enter company address"
                            autocomplete="street-address"
                            required>

                    </div>

                    @error('address')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- City -->

                <div class="form-group">

                    <label for="city">

                        City

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-city"></i>

                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="{{ old('city', $profile['city'] ?? '') }}"
                            placeholder="Enter company city"
                            autocomplete="address-level2"
                            required>

                    </div>

                    @error('city')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Area of Work -->

                <div class="form-group">

                    <label for="area_work">

                        Area of Work

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-briefcase"></i>

                        <input
                            type="text"
                            id="area_work"
                            name="area_work"
                            value="{{ old('area_work', $profile['area_work'] ?? '') }}"
                            placeholder="e.g. IT, Finance, Healthcare"
                            required>

                    </div>

                    @error('area_work')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>

                @endif


                <!-- =================================================
                     ACTIONS
                     ================================================= -->

                <div class="form-actions">

                    <a
                        href="{{ route('register.details') }}"
                        class="btn-secondary">

                        <i class="fas fa-arrow-left"></i>

                        Back

                    </a>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="profile-continue">

                        Continue

                        <i class="fas fa-arrow-right"></i>

                    </button>

                </div>

            </form>


            <!-- =================================================
                 LOGIN
                 ================================================= -->

            <div class="login-link">

                Already have an account?

                <a href="{{ route('login') }}">
                    Sign in
                </a>

            </div>

        </div>


        <!-- Footer -->

        <div class="registration-footer">

            <span>

                <i class="fas fa-lock"></i>

                Your information is securely protected

            </span>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>
    document
        .getElementById('profile-form')
        .addEventListener('submit', function() {

            const button =
                document.getElementById('profile-continue');

            button.disabled = true;

            button.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Saving...';

        });
</script>

@endsection