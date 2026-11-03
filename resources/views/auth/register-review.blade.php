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
                    <img class="cn-logo-image cn-logo-image--auth" src="{{ asset('assets/images/career_next.png') }}" alt="Career Next Logo">
                </span>
                <span>Career Next</span>
            </a>
        </div>

        <div class="registration-content">
            <div class="card-header">
                <div class="step-badge">
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                </div>
                <div>
                    <h1>Review Your Details</h1>
                    <p>Please review your information carefully before continuing.</p>
                </div>
            </div>

            <x-register-progress :current="5" />

            {{-- Account Type --}}
            <section class="review-section">
                <div class="review-section-header">
                    <div>
                        <h3><i class="fas fa-user-tag" aria-hidden="true"></i> Account Type</h3>
                        <p>Your selected registration type</p>
                    </div>
                </div>

                <div class="review-account-type">
                    @if (($accountType ?? '') === 'JobSeeker')
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <div>
                            <strong>Job Seeker</strong>
                            <span>Looking for jobs and career opportunities</span>
                        </div>
                    @else
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <div>
                            <strong>Employer</strong>
                            <span>Looking for candidates and employees</span>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Account Details --}}
            <section class="review-section">
                <div class="review-section-header">
                    <div>
                        <h3><i class="fas fa-lock" aria-hidden="true"></i> Account Details</h3>
                        <p>Your login and contact information</p>
                    </div>
                    <a href="{{ route('register.details') }}" class="review-edit">
                        <i class="fas fa-pen" aria-hidden="true"></i> Edit
                    </a>
                </div>

                <div class="review-grid">
                    <div class="review-item">
                        <span>Name</span>
                        <strong>{{ $registration['name'] ?? 'N/A' }}</strong>
                    </div>
                    <div class="review-item">
                        <span>Username</span>
                        <strong>{{ $registration['username'] ?? 'N/A' }}</strong>
                    </div>
                    <div class="review-item">
                        <span>Email</span>
                        <strong>{{ $registration['email'] ?? 'N/A' }}</strong>
                    </div>
                    <div class="review-item">
                        <span>Mobile</span>
                        <strong>{{ $registration['mobile'] ?? 'N/A' }}</strong>
                    </div>
                </div>
            </section>

            {{-- Profile Details --}}
            <section class="review-section">
                <div class="review-section-header">
                    <div>
                        <h3>
                            <i class="fas fa-address-card" aria-hidden="true"></i>
                            {{ ($accountType ?? '') === 'JobSeeker' ? 'Personal Details' : 'Company Details' }}
                        </h3>
                        <p>Your profile information</p>
                    </div>
                    <a href="{{ route('register.profile') }}" class="review-edit">
                        <i class="fas fa-pen" aria-hidden="true"></i> Edit
                    </a>
                </div>

                @if (($accountType ?? '') === 'JobSeeker')
                    <div class="review-grid">
                        <div class="review-item">
                            <span>City</span>
                            <strong>{{ $registration['profile']['city'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item">
                            <span>Gender</span>
                            <strong>{{ $registration['profile']['gender'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item">
                            <span>Date of Birth</span>
                            <strong>
                                @if (!empty($registration['profile']['birth_date']))
                                    {{ \Carbon\Carbon::parse($registration['profile']['birth_date'])->format('d M Y') }}
                                @else
                                    N/A
                                @endif
                            </strong>
                        </div>
                        <div class="review-item review-full">
                            <span>Address</span>
                            <strong>{{ $registration['profile']['address'] ?? 'N/A' }}</strong>
                        </div>
                    </div>
                @else
                    <div class="review-grid">
                        <div class="review-item">
                            <span>Contact Person</span>
                            <strong>{{ $registration['profile']['contact_person'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item">
                            <span>City</span>
                            <strong>{{ $registration['profile']['city'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item">
                            <span>Area of Work</span>
                            <strong>{{ $registration['profile']['area_work'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item review-full">
                            <span>Company Address</span>
                            <strong>{{ $registration['profile']['address'] ?? 'N/A' }}</strong>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Professional Details --}}
            @if (($accountType ?? '') === 'JobSeeker')
                <section class="review-section">
                    <div class="review-section-header">
                        <div>
                            <h3><i class="fas fa-briefcase" aria-hidden="true"></i> Professional Details</h3>
                            <p>Your professional information</p>
                        </div>
                        <a href="{{ route('register.professional') }}" class="review-edit">
                            <i class="fas fa-pen" aria-hidden="true"></i> Edit
                        </a>
                    </div>

                    <div class="review-grid">
                        <div class="review-item review-full">
                            <span>Highest Qualification</span>
                            <strong>{{ $registration['professional']['qualification'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="review-item">
                            <span>Resume</span>
                            <strong>
                                @if (!empty($registration['professional']['resume']))
                                    <i class="fas fa-check-circle" aria-hidden="true"></i> Uploaded
                                @else
                                    Not uploaded
                                @endif
                            </strong>
                        </div>
                        <div class="review-item">
                            <span>Profile Photo</span>
                            <strong>
                                @if (!empty($registration['professional']['photo']))
                                    <i class="fas fa-check-circle" aria-hidden="true"></i> Uploaded
                                @else
                                    Not uploaded
                                @endif
                            </strong>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Confirmation --}}
            <div class="review-confirmation">
                <form action="{{ route('register.review.store') }}" method="POST" id="reviewForm">
                    @csrf

                    <label class="confirmation-label" for="confirm_details">
                        <input
                            type="checkbox"
                            id="confirm_details"
                            name="confirm_details"
                            value="1"
                            required
                            @checked(old('confirm_details'))>
                        <span>
                            I confirm that the information provided above is accurate and belongs to me / my organization.
                        </span>
                    </label>

                    @error('confirm_details')
                        <div class="review-error" role="alert">{{ $message }}</div>
                    @enderror

                    <div class="review-actions">
                        <a href="{{ route('register.professional') }}" class="btn-secondary">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
                        </a>

                        <button type="submit" class="btn-primary" id="continueButton">
                            Continue to Verification
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reviewForm');
    const button = document.getElementById('continueButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            form.reportValidity();
            event.preventDefault();
            return;
        }

        if (button.disabled) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Preparing verification...';
    });
});
</script>
@endsection
