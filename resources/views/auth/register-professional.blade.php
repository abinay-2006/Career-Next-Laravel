@extends('layouts.auth')

@section('title', 'Professional Details')

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

                    <i class="fas fa-check"></i>

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

                    <i class="fas fa-graduation-cap"></i>

                    @else

                    <i class="fas fa-building"></i>

                    @endif

                </div>


                <div>

                    @if($accountType === 'JobSeeker')

                    <h1>
                        Professional details
                    </h1>

                    <p>
                        Add your professional information to improve your profile.
                    </p>

                    @else

                    <h1>
                        Company information
                    </h1>

                    <p>
                        Review your company information before continuing.
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

                @else

                <i class="fas fa-building"></i>

                @endif


                <div>

                    <strong>
                        {{ session('registration.name') }}
                    </strong>

                    <span>
                        {{ session('registration.email') }}
                    </span>

                </div>


                <a href="{{ route('register.profile') }}">
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
                action="{{ route('register.professional.store') }}"
                enctype="multipart/form-data"
                id="professional-form">

                @csrf


                @if($accountType === 'JobSeeker')

                <!-- =========================================
                         QUALIFICATION
                         ========================================= -->

                <div class="form-group">

                    <label for="qualification">

                        Highest Qualification

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-graduation-cap"></i>

                        <input
                            type="text"
                            id="qualification"
                            name="qualification"
                            value="{{ old('qualification', $professional['qualification'] ?? '') }}"
                            placeholder="e.g. BCA, B.Tech, MBA"
                            required>

                    </div>


                    @error('qualification')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- =========================================
                         RESUME
                         ========================================= -->

                <div class="form-group">

                    <label for="resume">

                        Resume

                        <span class="optional">
                            Optional
                        </span>

                    </label>


                    <div class="file-upload-box">

                        <input
                            type="file"
                            id="resume"
                            name="resume"
                            accept=".pdf,.doc,.docx">


                        <label
                            for="resume"
                            class="file-upload-label">

                            <div class="file-upload-icon">

                                <i class="fas fa-file-arrow-up"></i>

                            </div>


                            <div>

                                <strong>
                                    Upload your resume
                                </strong>

                                <span id="resume-name">

                                    PDF, DOC or DOCX · Maximum 5 MB

                                </span>

                            </div>


                            <i class="fas fa-cloud-arrow-up upload-arrow"></i>

                        </label>

                    </div>


                    @error('resume')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- =========================================
                         PROFILE PHOTO
                         ========================================= -->

                <div class="form-group">

                    <label for="photo">

                        Profile Photo

                        <span class="optional">
                            Optional
                        </span>

                    </label>


                    <div class="file-upload-box">

                        <input
                            type="file"
                            id="photo"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp">


                        <label
                            for="photo"
                            class="file-upload-label">

                            <div class="file-upload-icon">

                                <i class="fas fa-camera"></i>

                            </div>


                            <div>

                                <strong>
                                    Upload a profile photo
                                </strong>

                                <span id="photo-name">

                                    JPG, PNG or WEBP · Maximum 2 MB

                                </span>

                            </div>


                            <i class="fas fa-cloud-arrow-up upload-arrow"></i>

                        </label>

                    </div>


                    @error('photo')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Information -->

                <div class="registration-info-box">

                    <i class="fas fa-lightbulb"></i>

                    <div>

                        <strong>
                            Make your profile stand out
                        </strong>

                        <p>
                            Adding a resume and profile photo can help
                            employers understand your background and
                            professional experience.
                        </p>

                    </div>

                </div>


                @else

                <!-- =========================================
                         EMPLOYER
                         ========================================= -->

                <div class="professional-summary">

                    <div class="summary-title">

                        <i class="fas fa-building"></i>

                        <strong>
                            Company Information
                        </strong>

                    </div>


                    <div class="summary-grid">


                        <!-- Company -->

                        <div class="summary-item">

                            <span>
                                Company Name
                            </span>

                            <strong>
                                {{ session('registration.name') }}
                            </strong>

                        </div>


                        <!-- Contact -->

                        <div class="summary-item">

                            <span>
                                Contact Person
                            </span>

                            <strong>
                                {{ $profile['contact_person'] ?? '—' }}
                            </strong>

                        </div>


                        <!-- Email -->

                        <div class="summary-item">

                            <span>
                                Email
                            </span>

                            <strong>
                                {{ session('registration.email') }}
                            </strong>

                        </div>


                        <!-- Mobile -->

                        <div class="summary-item">

                            <span>
                                Mobile
                            </span>

                            <strong>
                                {{ session('registration.mobile') }}
                            </strong>

                        </div>


                        <!-- City -->

                        <div class="summary-item">

                            <span>
                                City
                            </span>

                            <strong>
                                {{ $profile['city'] ?? '—' }}
                            </strong>

                        </div>


                        <!-- Area -->

                        <div class="summary-item">

                            <span>
                                Area of Work
                            </span>

                            <strong>
                                {{ $profile['area_work'] ?? '—' }}
                            </strong>

                        </div>


                    </div>


                    <div class="summary-address">

                        <span>
                            Company Address
                        </span>

                        <strong>
                            {{ $profile['address'] ?? '—' }}
                        </strong>

                    </div>

                </div>


                <div class="registration-info-box">

                    <i class="fas fa-circle-info"></i>

                    <div>

                        <strong>
                            Company information looks good?
                        </strong>

                        <p>
                            You can review everything once more before
                            your email is verified.
                        </p>

                    </div>

                </div>

                @endif


                <!-- =================================================
                     ACTIONS
                     ================================================= -->

                <div class="form-actions">

                    <a
                        href="{{ route('register.profile') }}"
                        class="btn-secondary">

                        <i class="fas fa-arrow-left"></i>

                        Back

                    </a>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="professional-continue">

                        Continue

                        <i class="fas fa-arrow-right"></i>

                    </button>

                </div>

            </form>


            <!-- Login -->

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
    const professionalForm =
        document.getElementById('professional-form');


    if (professionalForm) {

        professionalForm.addEventListener('submit', function() {

            const button =
                document.getElementById('professional-continue');

            window.CareerNextButtonLoading?.start(button, 'Saving...');

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Resume filename
    |--------------------------------------------------------------------------
    */

    const resumeInput =
        document.getElementById('resume');

    if (resumeInput) {

        resumeInput.addEventListener('change', function() {

            const name =
                document.getElementById('resume-name');

            if (this.files.length > 0) {

                name.textContent =
                    this.files[0].name;

            } else {

                name.textContent =
                    'PDF, DOC or DOCX · Maximum 5 MB';

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Photo filename
    |--------------------------------------------------------------------------
    */

    const photoInput =
        document.getElementById('photo');

    if (photoInput) {

        photoInput.addEventListener('change', function() {

            const name =
                document.getElementById('photo-name');

            if (this.files.length > 0) {

                name.textContent =
                    this.files[0].name;

            } else {

                name.textContent =
                    'JPG, PNG or WEBP · Maximum 2 MB';

            }

        });

    }
</script>

@endsection
