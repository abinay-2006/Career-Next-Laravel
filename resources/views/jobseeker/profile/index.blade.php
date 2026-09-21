@extends('layouts.jobseeker')

@section('title', 'Job Seeker Profile')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">

        <div class="page-header-icon">
            <i class="fas fa-user"></i>
        </div>

        <div class="page-header-text">
            <h1>My Profile</h1>
            <p>View and manage your personal information</p>
        </div>

        <a href="{{ route('jobseeker.index') }}" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

    </div>


    <!-- ═══════════════════════════════════════
         PROFILE HERO
    ═══════════════════════════════════════ -->

    <div class="profile-hero">

        <!-- Avatar -->
        <div class="hero-avatar"
            onclick="openModal()"
            title="Change photo"
            style="cursor:pointer; position:relative;">

            @if (!empty($jobSeeker->Photo))

            <img
                src="{{ asset('upload/photos/' . $jobSeeker->Photo) }}"
                alt="Profile Photo">

            @else

            @if (!empty($initials))
            {{ $initials }}
            @else
            <i class="fas fa-user"></i>
            @endif

            @endif

            <!-- Photo hover overlay -->
            <div
                id="photoHoverOverlay"
                style="
                    position:absolute;
                    inset:0;
                    border-radius:50%;
                    background:rgba(0,0,0,0);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    transition:background .2s;
                    font-size:.85rem;
                    opacity:0;
                ">
                <i class="fas fa-camera" style="color:#fff"></i>
            </div>

        </div>


        <!-- Hero Information -->
        <div class="hero-info">

            <div class="hero-name">
                {{ $jobSeeker->JobSeekerName ?? 'Unknown' }}
            </div>


            <div class="hero-meta">

                @if (!empty($jobSeeker->Email))
                <span class="hero-pill">
                    <i class="fas fa-envelope"></i>
                    {{ $jobSeeker->Email }}
                </span>
                @endif


                @if (!empty($jobSeeker->Mobile))
                <span class="hero-pill">
                    <i class="fas fa-phone"></i>
                    {{ $jobSeeker->Mobile }}
                </span>
                @endif


                @if (!empty($jobSeeker->City))
                <span class="hero-pill">
                    <i class="fas fa-location-dot"></i>
                    {{ $jobSeeker->City }}
                </span>
                @endif

            </div>

        </div>


        <!-- Edit Profile -->
        <button
            type="button"
            class="btn-edit-profile"
            onclick="openModal()">
            <i class="fas fa-pen-to-square"></i>
            Edit Profile
        </button>

    </div>
    <!-- ═══════════════════════════════════════
     PROFILE COMPLETION
═══════════════════════════════════════ -->

    <div class="profile-page-completion">

        <!-- Header -->
        <div class="profile-completion-top">

            <div class="profile-completion-heading">

                <div class="profile-completion-page-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>

                <div>
                    <h2>Profile Completion</h2>

                    <p>
                        Keep your profile updated to get the most from Career Next.
                    </p>
                </div>

            </div>


            <!-- Percentage -->
            <div class="profile-page-percentage">
                {{ $profileCompletion }}%
            </div>

        </div>


        <!-- Progress -->
        <div class="profile-page-progress">

            <div
                class="profile-page-progress-fill"
                style="width: {{ $profileCompletion }}%;">
            </div>

        </div>


        <!-- Status -->
        <div class="profile-page-completion-bottom">

            <div class="profile-page-count">

                <strong>{{ $completedItems }}</strong>
                of
                <strong>{{ $totalItems }}</strong>

                profile sections completed

            </div>


            @if($profileCompletion >= 100)

            <div class="profile-page-complete">

                <i class="fas fa-circle-check"></i>

                Profile complete

            </div>

            @else

            <div class="profile-page-incomplete">

                <i class="fas fa-circle-exclamation"></i>

                {{ count($missingProfileItems) }} remaining

            </div>

            @endif

        </div>


        <!-- Missing Items -->
        @if(count($missingProfileItems) > 0)

        <div class="profile-page-missing">

            <div class="profile-page-missing-title">

                <i class="fas fa-list-check"></i>

                Complete these sections

            </div>


            <div class="profile-page-missing-list">

                @foreach($missingProfileItems as $item)

                <span class="profile-page-missing-item">

                    <i class="fas fa-circle"></i>

                    {{ $item }}

                </span>

                @endforeach

            </div>

        </div>

        @endif

    </div>


    <!-- ═══════════════════════════════════════
     INFORMATION CARDS
═══════════════════════════════════════ -->

    <div class="info-grid">


        <!-- ═══════════════════════════════════
             PERSONAL INFORMATION
        ═══════════════════════════════════ -->

        <div class="info-card">

            <div class="info-card-title">
                <i class="fas fa-circle-info"></i>
                Personal Information
            </div>


            <!-- Full Name -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-user"></i>
                    Full Name
                </span>

                <span class="info-value">
                    {{ $jobSeeker->JobSeekerName ?? '—' }}
                </span>

            </div>


            <!-- Gender -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-venus-mars"></i>
                    Gender
                </span>

                <span class="info-value">

                    @if (!empty($jobSeeker->Gender))

                    <span class="gender-badge">

                        <i class="fas
                                {{ strtolower($jobSeeker->Gender) === 'female'
                                    ? 'fa-venus'
                                    : 'fa-mars'
                                }}">
                        </i>

                        {{ $jobSeeker->Gender }}

                    </span>

                    @else

                    —

                    @endif

                </span>

            </div>


            <!-- Birth Date -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-cake-candles"></i>
                    Birth Date
                </span>

                <span class="info-value">

                    @if (!empty($jobSeeker->BirthDate))

                    {{ \Carbon\Carbon::parse($jobSeeker->BirthDate)->format('d M Y') }}

                    @else

                    —

                    @endif

                </span>

            </div>


            <!-- Qualification -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-graduation-cap"></i>
                    Qualification
                </span>

                <span class="info-value">

                    @if (!empty($jobSeeker->Qualification))

                    <span class="qual-badge">
                        {{ $jobSeeker->Qualification }}
                    </span>

                    @else

                    —

                    @endif

                </span>

            </div>

        </div>


        <!-- ═══════════════════════════════════
             CONTACT INFORMATION
        ═══════════════════════════════════ -->

        <div class="info-card">

            <div class="info-card-title">
                <i class="fas fa-address-card"></i>
                Contact Details
            </div>


            <!-- Email -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-envelope"></i>
                    Email
                </span>

                <span class="info-value">
                    {{ $jobSeeker->Email ?? '—' }}
                </span>

            </div>


            <!-- Mobile -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-phone"></i>
                    Mobile
                </span>

                <span class="info-value">
                    {{ $jobSeeker->Mobile ?? '—' }}
                </span>

            </div>


            <!-- City -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-city"></i>
                    City
                </span>

                <span class="info-value">
                    {{ $jobSeeker->City ?? '—' }}
                </span>

            </div>


            <!-- Address -->
            <div class="info-row">

                <span class="info-label">
                    <i class="fas fa-location-dot"></i>
                    Address
                </span>

                <span
                    class="info-value"
                    style="font-size:.82rem">
                    {{ $jobSeeker->Address ?? '—' }}
                </span>

            </div>

        </div>

    </div>


    <!-- ═══════════════════════════════════════
     RESUME CARD
═══════════════════════════════════════ -->

    <div class="resume-card">

        <div class="resume-left">

            <div class="resume-icon">
                <i class="fas fa-file-pdf"></i>
            </div>

            <div class="resume-details">

                <div class="resume-label">
                    My Resume
                </div>

                <div class="resume-sub">

                    @if ($resume)

                    <span class="resume-file-name">
                        <i class="fas fa-file-lines"></i>
                        {{ $resume->OriginalFileName }}
                    </span>

                    @if ($resume->VerificationStatus === 'approved')

                    <span class="resume-status resume-status-approved">
                        <i class="fas fa-circle-check"></i>
                        Verified
                    </span>

                    @elseif ($resume->VerificationStatus === 'rejected')

                    <span class="resume-status resume-status-rejected">
                        <i class="fas fa-circle-xmark"></i>
                        Rejected
                    </span>

                    @else

                    <span class="resume-status resume-status-pending">
                        <i class="fas fa-clock"></i>
                        Pending verification
                    </span>

                    @endif

                    @else

                    <span class="resume-no-file">
                        No resume uploaded yet
                    </span>

                    @endif

                </div>

            </div>

        </div>


        <!-- Resume Actions -->

        <div class="resume-actions">

            @if ($resume)

            <!-- View -->
            <a
                href="{{ asset('upload/' . $resume->FileName) }}"
                target="_blank"
                class="resume-action-btn resume-view-btn">

                <i class="fas fa-eye"></i>
                <span>View Resume</span>

            </a>


            <!-- Replace -->
            <button
                type="button"
                class="resume-action-btn resume-upload-btn"
                onclick="openResumeModal()">

                <i class="fas fa-cloud-arrow-up"></i>
                <span>Replace</span>

            </button>


            <!-- Remove
                 IMPORTANT:
                 This does NOT remove the resume.
                 It ONLY opens the confirmation modal.
            -->
            <button
                type="button"
                class="resume-action-btn resume-remove-btn"
                onclick="openRemoveResumeModal()">

                <i class="fas fa-trash-can"></i>
                <span>Remove</span>

            </button>


            <!-- Hidden delete form -->
            <form
                method="POST"
                action="{{ route('jobseeker.profile.resume.remove') }}"
                id="removeResumeForm"
                style="display:none;">

                @csrf
                @method('DELETE')

            </form>

            @else

            <!-- Upload Resume -->
            <button
                type="button"
                class="resume-action-btn resume-upload-primary"
                onclick="openResumeModal()">

                <i class="fas fa-cloud-arrow-up"></i>
                <span>Upload Resume</span>

            </button>

            @endif

        </div>

    </div>

</div>
<!-- ═══════════════════════════════════════
     EDIT PROFILE MODAL
═══════════════════════════════════════ -->

<div
    class="profile-modal-overlay"
    id="editModal">

    <div
        class="profile-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle">
        <!-- Modal Header -->
        <div class="profile-modal-header">

            <div class="profile-modal-header-left">

                <div class="profile-modal-header-icon">
                    <i class="fas fa-user-pen"></i>
                </div>

                <div>

                    <div
                        class="modal-title"
                        id="modalTitle">
                        Edit Profile
                    </div>

                    <div class="profile-modal-subtitle">
                        Update your personal information
                    </div>

                </div>

            </div>


            <button
                type="button"
                class="btn-close"
                onclick="closeModal()"
                aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- Modal Form -->
        <form
            method="POST"
            action="{{ route('jobseeker.profile.update') }}"
            id="editForm"
            enctype="multipart/form-data">

            @csrf
            @method('PUT')


            <!-- Modal Body -->
            <div class="profile-modal-body">
                <!-- Profile Photo -->
                <div class="form-group full">

                    <label class="form-label" for="Photo">
                        <i class="fas fa-camera"></i>
                        Profile Photo
                    </label>

                    <div class="profile-photo-upload">

                        <div class="profile-photo-preview" id="profilePhotoPreview">

                            @if (!empty($jobSeeker->Photo))
                            <img
                                src="{{ asset('upload/photos/' . $jobSeeker->Photo) }}"
                                alt="Profile Photo">
                            @else
                            <i class="fas fa-user"></i>
                            @endif

                        </div>

                        <div class="profile-photo-upload-content">

                            <input
                                type="file"
                                name="Photo"
                                id="Photo"
                                accept="image/jpeg,image/png,image/jpg"
                                onchange="previewProfilePhoto(this)">

                            <small style="color: #ccc; font-size: 0.8rem;">
                                JPG, JPEG or PNG. Maximum size: 2 MB.
                            </small>

                        </div>

                    </div>

                </div>
                <div class="form-grid">


                    <!-- Name -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="JobSeekerName">
                            <i class="fas fa-user"></i>
                            Full Name
                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="JobSeekerName"
                            id="JobSeekerName"
                            value="{{ old('JobSeekerName', $jobSeeker->JobSeekerName) }}"
                            required>

                    </div>

                    <!-- Username -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="UserName">
                            <i class="fas fa-at"></i>
                            Username
                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="UserName"
                            id="UserName"
                            value="{{ old('UserName', $jobSeeker->UserName) }}"
                            required>

                    </div>

                    <!-- Email -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="Email">
                            <i class="fas fa-envelope"></i>
                            Email
                        </label>

                        <input
                            class="form-input"
                            type="email"
                            name="Email"
                            id="Email"
                            value="{{ old('Email', $jobSeeker->Email) }}"
                            required>

                    </div>

                    <!-- Mobile -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="Mobile">
                            <i class="fas fa-phone"></i>
                            Mobile
                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="Mobile"
                            id="Mobile"
                            value="{{ old('Mobile', $jobSeeker->Mobile) }}">

                    </div>

                    <!-- Gender -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="Gender">
                            <i class="fas fa-venus-mars"></i>
                            Gender
                        </label>

                        <select
                            class="form-input"
                            name="Gender"
                            id="Gender">

                            <option value="">
                                Select Gender
                            </option>

                            <option
                                value="Male"
                                {{ old('Gender', $jobSeeker->Gender) === 'Male' ? 'selected' : '' }}>
                                Male
                            </option>

                            <option
                                value="Female"
                                {{ old('Gender', $jobSeeker->Gender) === 'Female' ? 'selected' : '' }}>
                                Female
                            </option>

                            <option
                                value="Other"
                                {{ old('Gender', $jobSeeker->Gender) === 'Other' ? 'selected' : '' }}>
                                Other
                            </option>

                        </select>

                    </div>

                    <!-- Birth Date -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="BirthDate">
                            <i class="fas fa-cake-candles"></i>
                            Birth Date
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="BirthDate"
                            id="BirthDate"
                            value="{{ old('BirthDate', $jobSeeker->BirthDate) }}">

                    </div>

                    <!-- City -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="City">
                            <i class="fas fa-city"></i>
                            City
                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="City"
                            id="City"
                            value="{{ old('City', $jobSeeker->City) }}">

                    </div>


                    <!-- Qualification -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="Qualification">
                            <i class="fas fa-graduation-cap"></i>
                            Qualification
                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="Qualification"
                            id="Qualification"
                            value="{{ old('Qualification', $jobSeeker->Qualification) }}">

                    </div>


                    <!-- Address -->
                    <div class="form-group full">

                        <label
                            class="form-label"
                            for="Address">
                            <i class="fas fa-location-dot"></i>
                            Address
                        </label>

                        <textarea
                            class="form-textarea"
                            name="Address"
                            id="Address"
                            rows="3">{{ old('Address', $jobSeeker->Address) }}</textarea>

                    </div>



                </div>

            </div>


            <!-- Modal Footer -->
            <div class="profile-modal-footer">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeModal()">
                    <i class="fas fa-xmark"></i>
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn-submit">
                    <i class="fas fa-floppy-disk"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<!-- ═══════════════════════════════════════
     RESUME UPLOAD MODAL
═══════════════════════════════════════ -->

<div
    class="modal-overlay"
    id="resumeModal"
    onclick="handleResumeOverlayClick(event)">

    <div
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="resumeModalTitle">

        <!-- Header -->
        <div class="profile-modal-header">

            <div class="profile-modal-header-left">

                <div class="profile-modal-header-icon">
                    <i class="fas fa-file-arrow-up"></i>
                </div>

                <div>
                    <div
                        class="modal-title"
                        id="resumeModalTitle">
                        Upload Resume
                    </div>

                    <div class="profile-modal-subtitle">
                        Upload your latest resume
                    </div>
                </div>

            </div>

            <button
                type="button"
                class="btn-close"
                onclick="closeResumeModal()"
                aria-label="Close">

                <i class="fas fa-xmark"></i>

            </button>

        </div>


        <!-- Resume Form -->
        <form
            method="POST"
            action="{{ route('jobseeker.profile.resume.update') }}"
            enctype="multipart/form-data">

            @csrf
            @method('PUT')

            <div class="profile-modal-body">

                <div class="form-group">

                    <label
                        class="form-label"
                        for="Resume">

                        <i class="fas fa-file-pdf"></i>
                        Resume

                    </label>

                    <input
                        type="file"
                        class="form-input"
                        name="Resume"
                        id="Resume"
                        accept=".pdf,.doc,.docx"
                        required>

                    <small style="
                        display:block;
                        margin-top:8px;
                        color:rgba(255,255,255,.6);
                    ">
                        Accepted formats: PDF, DOC, DOCX
                    </small>

                </div>

            </div>


            <!-- Footer -->
            <div class="profile-modal-footer">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeResumeModal()">

                    <i class="fas fa-xmark"></i>
                    Cancel

                </button>

                <button
                    type="submit"
                    class="btn-submit">

                    <i class="fas fa-upload"></i>
                    Upload Resume

                </button>

            </div>

        </form>

    </div>

</div>
<!-- ═══════════════════════════════════════
     REMOVE RESUME CONFIRMATION MODAL
═══════════════════════════════════════ -->

<div
    class="modal-overlay"
    id="removeResumeModal"
    onclick="handleRemoveResumeOverlayClick(event)">

    <div
        class="modal confirmation-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="removeResumeTitle">

        <!-- Header -->

        <div class="profile-modal-header">

            <div class="profile-modal-header-left">

                <div
                    class="modal-header-icon"
                    style="background:rgba(239,68,68,.18);">

                    <i
                        class="fas fa-trash"
                        style="color:#fca5a5;">
                    </i>

                </div>

                <div>

                    <div
                        class="modal-title"
                        id="removeResumeTitle">

                        Remove Resume

                    </div>

                    <div class="profile-modal-subtitle">

                        This action cannot be undone

                    </div>

                </div>

            </div>


            <button
                type="button"
                class="btn-close"
                onclick="closeRemoveResumeModal()"
                aria-label="Close">

                <i class="fas fa-xmark"></i>

            </button>

        </div>


        <!-- Body -->

        <div class="profile-modal-body">

            <div class="confirmation-content">

                <div class="confirmation-icon">

                    <i class="fas fa-file-circle-xmark"></i>

                </div>

                <div class="confirmation-text">

                    <h3 style="color:rgba(255,255,255,.9);">Remove your resume?</h3>

                    <p style="color:rgba(255,255,255,.7);">
                        Are you sure you want to remove your uploaded resume?
                        You will need to upload it again if you want to use it
                        in the future.
                    </p>

                </div>

            </div>

        </div>


        <!-- Footer -->

        <div class="profile-modal-footer">

            <button
                type="button"
                class="btn-cancel"
                onclick="closeRemoveResumeModal()">

                <i class="fas fa-xmark"></i>
                Cancel

            </button>


            <button
                type="button"
                class="btn-delete-confirm"
                onclick="submitRemoveResume()">

                <i class="fas fa-trash"></i>
                Remove Resume

            </button>

        </div>

    </div>

</div>
@endsection
@section('scripts')
<script>
    /* ── Profile Photo Preview ── */
    function previewProfilePhoto(input) {

        const preview = document.getElementById('profilePhotoPreview');

        if (!preview) {
            return;
        }

        if (input.files && input.files[0]) {

            const reader = new FileReader();

            reader.onload = function(e) {

                preview.innerHTML =
                    '<img src="' +
                    e.target.result +
                    '" alt="Profile Photo Preview">';

            };

            reader.readAsDataURL(input.files[0]);
        }
    }
    /* ── Avatar hover overlay ── */
    const heroAvatar = document.querySelector('.hero-avatar');
    const overlay = document.getElementById('photoHoverOverlay');
    if (heroAvatar && overlay) {
        heroAvatar.addEventListener('mouseenter', () => {
            overlay.style.opacity = '1';
            overlay.style.background = 'rgba(0,0,0,0.45)';
        });
        heroAvatar.addEventListener('mouseleave', () => {
            overlay.style.opacity = '0';
            overlay.style.background = 'rgba(0,0,0,0)';
        });
    }

    /* ── Navbar dropdown ── */
    function toggleDropdown() {
        document.getElementById('profileDropdown').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('profileDropdown');
        if (!dd.contains(e.target)) dd.classList.remove('open');
    });

    /* ── Toast ── */
    const popupFlag = "{{ session('popup') }}";
    let toastTimer;

    function showToast() {
        const toast = document.getElementById('toast');
        const bar = document.getElementById('toastProgress');
        toast.classList.add('show');
        bar.style.transition = 'none';
        bar.style.transform = 'scaleX(1)';
        requestAnimationFrame(() => requestAnimationFrame(() => {
            bar.style.transition = 'transform 4s linear';
            bar.style.transform = 'scaleX(0)';
        }));
        toastTimer = setTimeout(closeToast, 4000);
    }

    function closeToast() {
        clearTimeout(toastTimer);
        document.getElementById('toast').classList.remove('show');
    }

    if (popupFlag) window.addEventListener('load', () => setTimeout(showToast, 300));

    /* ==========================================================
   EDIT PROFILE MODAL
========================================================== */

    function openModal() {

        const modal = document.getElementById('editModal');

        if (!modal) {
            console.error('Edit profile modal not found.');
            return;
        }

        modal.classList.add('show');

        document.body.style.overflow = 'hidden';
    }


    function closeModal() {

        const modal = document.getElementById('editModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        document.body.style.overflow = '';
    }


    /* Close when clicking the dark overlay */
    const editModal = document.getElementById('editModal');

    if (editModal) {

        editModal.addEventListener('click', function(event) {

            if (event.target === editModal) {
                closeModal();
            }

        });

    }


    /* ── Resume Modal ── */

    function openResumeModal() {
        const modal = document.getElementById('resumeModal');

        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeResumeModal() {
        const modal = document.getElementById('resumeModal');

        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    function handleResumeOverlayClick(event) {
        if (event.target.id === 'resumeModal') {
            closeResumeModal();
        }
    }
    /* ==========================================================
   REMOVE RESUME CONFIRMATION MODAL
========================================================== */

    function openRemoveResumeModal() {

        const modal = document.getElementById('removeResumeModal');

        if (modal) {

            modal.classList.add('show');

            document.body.style.overflow = 'hidden';
        }
    }


    function closeRemoveResumeModal() {

        const modal = document.getElementById('removeResumeModal');

        if (modal) {

            modal.classList.remove('show');

            document.body.style.overflow = '';
        }
    }


    function handleRemoveResumeOverlayClick(event) {

        if (event.target.id === 'removeResumeModal') {

            closeRemoveResumeModal();
        }
    }


    function submitRemoveResume() {

        const form = document.getElementById('removeResumeForm');

        if (form) {

            form.submit();
        }
    }



    /* ==========================================================
   PROFILE COMPLETION DEEP LINK
========================================================== */

    document.addEventListener('DOMContentLoaded', function() {

        const section = @json($section ?? null);

        if (!section) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Personal information
        |--------------------------------------------------------------------------
        */

        if (section === 'personal') {

            const modal = document.getElementById('editModal');

            if (modal) {
                openModal();
            }

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Resume
        |--------------------------------------------------------------------------
        */

        if (section === 'resume') {

            const modal = document.getElementById('resumeModal');

            if (modal) {
                openResumeModal();
            }

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Profile Photo
        |--------------------------------------------------------------------------
        */

        if (section === 'photo') {

            const modal = document.getElementById('editModal');

            if (modal) {
                openModal();

                setTimeout(function() {

                    const photoInput =
                        document.getElementById('Photo');

                    if (photoInput) {
                        photoInput.focus();
                    }

                }, 300);
            }

            return;
        }

    });
</script>
@endsection