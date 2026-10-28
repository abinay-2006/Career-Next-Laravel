@extends('layouts.auth')

@section('title', 'Review Registration')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/register.css') }}">
@endpush

@section('content')

<div class="register-page">

    <div class="register-container">

        <div class="register-header">
            <a href="{{ route('register.index') }}" class="register-logo">
                <span class="logo-icon">
                    <img src="{{ asset('images/CareerNext.png') }}" alt="Career Next Logo" width="42" height="42">
                </span>
                <span>Career Next</span>
            </a>
        </div>

    <div class="registration-card">

        <!-- Header -->
        <div class="registration-header">

            <div class="registration-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>

            <h1>Review Your Details</h1>

            <p>
                Please review your information carefully before continuing.
            </p>

        </div>


        <!-- Progress -->
        <div class="registration-progress">

            <div class="progress-step completed">
                <span>1</span>
                <small>Account</small>
            </div>

            <div class="progress-line completed"></div>

            <div class="progress-step completed">
                <span>2</span>
                <small>Details</small>
            </div>

            <div class="progress-line completed"></div>

            <div class="progress-step completed">
                <span>3</span>
                <small>Profile</small>
            </div>

            <div class="progress-line completed"></div>

            <div class="progress-step active">
                <span>4</span>
                <small>Review</small>
            </div>

            <div class="progress-line"></div>

            <div class="progress-step">
                <span>5</span>
                <small>Verify</small>
            </div>

        </div>


        <!-- Account Type -->
        <div class="review-section">

            <div class="review-section-header">

                <div>
                    <h3>
                        <i class="fas fa-user-tag"></i>
                        Account Type
                    </h3>

                    <p>
                        Your selected registration type
                    </p>
                </div>

            </div>

            <div class="review-account-type">

                @if($accountType === 'JobSeeker')

                <i class="fas fa-user"></i>

                <div>
                    <strong>Job Seeker</strong>
                    <span>Looking for jobs and career opportunities</span>
                </div>

                @else

                <i class="fas fa-building"></i>

                <div>
                    <strong>Employer</strong>
                    <span>Looking for candidates and employees</span>
                </div>

                @endif

            </div>

        </div>


        <!-- Account Details -->
        <div class="review-section">

            <div class="review-section-header">

                <div>
                    <h3>
                        <i class="fas fa-lock"></i>
                        Account Details
                    </h3>

                    <p>
                        Your login and contact information
                    </p>
                </div>

                <a href="{{ route('register.details') }}" class="review-edit">
                    <i class="fas fa-pen"></i>
                    Edit
                </a>

            </div>


            <div class="review-grid">

                <div class="review-item">
                    <span>Name</span>
                    <strong>
                        {{ $registration['name'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Username</span>
                    <strong>
                        {{ $registration['username'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Email</span>
                    <strong>
                        {{ $registration['email'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Mobile</span>
                    <strong>
                        {{ $registration['mobile'] }}
                    </strong>
                </div>

            </div>

        </div>


        <!-- Profile Details -->
        <div class="review-section">

            <div class="review-section-header">

                <div>
                    <h3>
                        <i class="fas fa-address-card"></i>
                        {{ $accountType === 'JobSeeker' ? 'Personal Details' : 'Company Details' }}
                    </h3>

                    <p>
                        Your profile information
                    </p>
                </div>

                <a href="{{ route('register.profile') }}" class="review-edit">
                    <i class="fas fa-pen"></i>
                    Edit
                </a>

            </div>


            @if($accountType === 'JobSeeker')

            <div class="review-grid">

                <div class="review-item">
                    <span>City</span>
                    <strong>
                        {{ $registration['profile']['city'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Gender</span>
                    <strong>
                        {{ $registration['profile']['gender'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Date of Birth</span>
                    <strong>
                        {{ \Carbon\Carbon::parse($registration['profile']['birth_date'])->format('d M Y') }}
                    </strong>
                </div>

                <div class="review-item review-full">
                    <span>Address</span>
                    <strong>
                        {{ $registration['profile']['address'] }}
                    </strong>
                </div>

            </div>

            @else

            <div class="review-grid">

                <div class="review-item">
                    <span>Contact Person</span>
                    <strong>
                        {{ $registration['profile']['contact_person'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>City</span>
                    <strong>
                        {{ $registration['profile']['city'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Area of Work</span>
                    <strong>
                        {{ $registration['profile']['area_work'] }}
                    </strong>
                </div>

                <div class="review-item review-full">
                    <span>Company Address</span>
                    <strong>
                        {{ $registration['profile']['address'] }}
                    </strong>
                </div>

            </div>

            @endif

        </div>


        <!-- Professional Details -->
        <div class="review-section">

            <div class="review-section-header">

                <div>
                    <h3>
                        <i class="fas fa-briefcase"></i>
                        Professional Details
                    </h3>

                    <p>
                        Your professional information
                    </p>
                </div>

                <a href="{{ route('register.professional') }}" class="review-edit">
                    <i class="fas fa-pen"></i>
                    Edit
                </a>

            </div>


            @if($accountType === 'JobSeeker')

            <div class="review-grid">

                <div class="review-item review-full">
                    <span>Highest Qualification</span>
                    <strong>
                        {{ $registration['professional']['qualification'] }}
                    </strong>
                </div>

                <div class="review-item">
                    <span>Resume</span>

                    <strong>
                        @if(!empty($registration['professional']['resume']))
                        <i class="fas fa-check-circle"></i>
                        Uploaded
                        @else
                        Not uploaded
                        @endif
                    </strong>
                </div>

                <div class="review-item">
                    <span>Profile Photo</span>

                    <strong>
                        @if(!empty($registration['professional']['photo']))
                        <i class="fas fa-check-circle"></i>
                        Uploaded
                        @else
                        Not uploaded
                        @endif
                    </strong>
                </div>

            </div>

            @else

            <div class="review-complete">

                <i class="fas fa-check-circle"></i>

                <div>
                    <strong>Company information completed</strong>
                    <span>
                        Your company details are ready for verification.
                    </span>
                </div>

            </div>

            @endif

        </div>


        <!-- Confirmation -->
        <div class="review-confirmation">

            <form
                action="{{ route('register.review.store') }}"
                method="POST"
                id="reviewForm">

                @csrf

                <label class="confirmation-label">

                    <input
                        type="checkbox"
                        name="confirm_details"
                        value="1"
                        required>

                    <span>
                        I confirm that the information provided above is
                        accurate and belongs to me / my organization.
                    </span>

                </label>


                @error('confirm_details')
                <div class="review-error">
                    {{ $message }}
                </div>
                @enderror


                <div class="review-actions">

                    <a
                        href="{{ route('register.professional') }}"
                        class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>

                    <button
                        type="submit"
                        class="btn-primary"
                        id="continueButton">
                        Continue to Verification
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.getElementById('reviewForm');
        const button = document.getElementById('continueButton');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function() {

            if (!form.checkValidity()) {
                return;
            }

            button.disabled = true;

            button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            Preparing verification...
        `;
        });

    });
</script>

@endsection
