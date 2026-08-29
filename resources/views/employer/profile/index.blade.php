@extends('layouts.employer')

@section('title', 'Employer Profile')

@section('content')

<!-- ═══════════════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════════════ -->

<div class="main-content">

    <!-- ═════════════════════════════════════════════════════
         ALERTS
    ══════════════════════════════════════════════════════ -->

    @if(session('success'))
    <div class="alert alert-success">
        <i class="fas fa-circle-check"></i>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger">

        <strong>
            <i class="fas fa-triangle-exclamation"></i>
            Please correct the following:
        </strong>

        <ul>
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>

    </div>
    @endif


    <!-- ═════════════════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════════════════ -->

    <div class="page-header">

        <div class="page-header-icon">
            <i class="fas fa-building"></i>
        </div>

        <div class="page-header-text">

            <h1>Company Profile</h1>

            <p>
                View and manage your employer details
            </p>

        </div>

        <a
            href="{{ route('employer.dashboard') }}"
            class="back-btn">

            <i class="fas fa-arrow-left"></i>

            Back to Panel

        </a>

    </div>


    <!-- ═════════════════════════════════════════════════════
         PROFILE HERO
    ══════════════════════════════════════════════════════ -->

    <div class="profile-hero">

        <!-- Company Avatar -->

        <div
            class="hero-avatar employer-avatar"
            onclick="openModal()"
            title="Edit company profile"
            style="cursor:pointer; position:relative;">

            @if (!empty($employer->Photo))
            <img
                src="{{ asset('upload/photos/' . $employer->Photo) }}"
                alt="Company Profile Photo">
            @else
            <i class="fas fa-building"></i>
            @endif

            <!-- Hover Overlay -->

            <div
                id="companyHoverOverlay"
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

                <i
                    class="fas fa-pen"
                    style="color:#fff;">
                </i>

            </div>

        </div>


        <!-- Hero Information -->

        <div class="hero-info">

            <div class="hero-name">

                {{ $employer->CompanyName ?? $employer->UserName ?? 'Employer' }}

            </div>


            <div class="hero-meta">

                @if(!empty($employer->Email))

                <span class="hero-pill">

                    <i class="fas fa-envelope"></i>

                    {{ $employer->Email }}

                </span>

                @endif


                @if(!empty($employer->Mobile))

                <span class="hero-pill">

                    <i class="fas fa-phone"></i>

                    {{ $employer->Mobile }}

                </span>

                @endif


                @if(!empty($employer->City))

                <span class="hero-pill">

                    <i class="fas fa-location-dot"></i>

                    {{ $employer->City }}

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


    <!-- ═════════════════════════════════════════════════════
         INFORMATION CARDS
    ══════════════════════════════════════════════════════ -->

    <div class="info-grid">


        <!-- ═════════════════════════════════════════════════
             COMPANY INFORMATION
        ══════════════════════════════════════════════════ -->

        <div class="info-card">

            <div class="info-card-title">

                <i class="fas fa-building"></i>

                Company Information

            </div>


            <!-- Company Name -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-building"></i>

                    Company Name

                </span>

                <span
                    class="info-value">

                    {{ $employer->CompanyName ?? '—' }}

                </span>

            </div>


            <!-- Contact Person -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-user-tie"></i>

                    Contact Person

                </span>

                <span class="info-value">

                    {{ $employer->ContactPerson ?? '—' }}

                </span>

            </div>


            <!-- Area of Work -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-briefcase"></i>

                    Area of Work

                </span>

                <span class="info-value">

                    @if(!empty($employer->Area_Work))

                    <span class="qual-badge">

                        {{ $employer->Area_Work }}

                    </span>

                    @else

                    —

                    @endif

                </span>

            </div>


            <!-- Username -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-at"></i>

                    Username

                </span>

                <span class="info-value">

                    {{ $employer->UserName ?? '—' }}

                </span>

            </div>


            <!-- Account Status -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-circle-check"></i>

                    Account Status

                </span>

                <span class="info-value">

                    <span class="gender-badge">

                        <i class="fas fa-circle"></i>

                        Active Account

                    </span>

                </span>

            </div>

        </div>


        <!-- ═════════════════════════════════════════════════
             CONTACT DETAILS
        ══════════════════════════════════════════════════ -->

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

                    {{ $employer->Email ?? '—' }}

                </span>

            </div>


            <!-- Mobile -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-phone"></i>

                    Mobile

                </span>

                <span class="info-value">

                    {{ $employer->Mobile ?? '—' }}

                </span>

            </div>


            <!-- City -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-city"></i>

                    City

                </span>

                <span class="info-value">

                    {{ $employer->City ?? '—' }}

                </span>

            </div>


            <!-- Address -->

            <div class="info-row">

                <span class="info-label">

                    <i class="fas fa-location-dot"></i>

                    Address

                </span>

                <span class="info-value">
                    {{ $employer->Address ?? '—' }}
                </span>

            </div>

        </div>

    </div>


</div>
<!-- ═══════════════════════════════════════════════════════
     / MAIN
═══════════════════════════════════════════════════════ -->


<!-- ═══════════════════════════════════════════════════════
     EDIT PROFILE MODAL
═══════════════════════════════════════════════════════ -->

<div
    class="profile-modal-overlay"
    id="editModal">

    <div
        class="profile-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle">


        <!-- ═════════════════════════════════════════════════
             MODAL HEADER
        ══════════════════════════════════════════════════ -->

        <div class="profile-modal-header">

            <div class="profile-modal-header-left">

                <div class="profile-modal-header-icon">

                    <i class="fas fa-building"></i>

                </div>

                <div>

                    <div
                        class="modal-title"
                        id="modalTitle">

                        Edit Company Profile

                    </div>

                    <div class="profile-modal-subtitle">

                        Update your company details

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


        <!-- ═════════════════════════════════════════════════
             FORM
        ══════════════════════════════════════════════════ -->

        <form
            method="POST"
            action="{{ route('employer.profile.update') }}"
            id="editForm"
            enctype="multipart/form-data">

            @csrf

            @method('PUT')


            <!-- ═════════════════════════════════════════════
                 MODAL BODY
            ══════════════════════════════════════════════ -->

            <div class="profile-modal-body">

                <div class="form-grid">

                    <!-- Company Logo -->

                    <div class="profile-logo-upload">

                        <div class="profile-logo-preview" id="logoPreview">

                            @if (!empty($employer->Photo))
                            <img
                                src="{{ asset('upload/photos/' . $employer->Photo) }}"
                                alt="Company Logo">
                            @else
                            <i class="fas fa-building"></i>
                            @endif

                        </div>

                        <div class="profile-logo-content">

                            <div class="profile-logo-title">
                                Company Logo
                            </div>

                            <div class="profile-logo-description">
                                Upload your company logo. JPG, JPEG, PNG or WEBP.
                                Maximum size: 2 MB.
                            </div>

                            <label
                                for="Photo"
                                class="profile-logo-button">

                                <i class="fas fa-camera"></i>

                                Choose Logo

                            </label>

                            <input
                                type="file"
                                name="Photo"
                                id="Photo"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                hidden>

                            <div
                                class="profile-logo-file-name"
                                id="logoFileName">

                                @if (!empty($employer->Photo))
                                Current logo
                                @else
                                No logo selected
                                @endif

                            </div>

                        </div>

                    </div>
                    <!-- Company Name -->

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="CompanyName">

                            <i class="fas fa-building"></i>

                            Company Name

                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="CompanyName"
                            id="CompanyName"
                            value="{{ old('CompanyName', $employer->CompanyName) }}"
                            placeholder="Enter company name"
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
                            value="{{ old('UserName', $employer->UserName) }}"
                            placeholder="Enter username"
                            required>

                    </div>


                    <!-- Contact Person -->

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="ContactPerson">

                            <i class="fas fa-user-tie"></i>

                            Contact Person

                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="ContactPerson"
                            id="ContactPerson"
                            value="{{ old('ContactPerson', $employer->ContactPerson) }}"
                            placeholder="Enter contact person"
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
                            value="{{ old('Mobile', $employer->Mobile) }}"
                            placeholder="Enter mobile number"
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
                            value="{{ old('Email', $employer->Email) }}"
                            placeholder="Enter email address"
                            required>

                    </div>


                    <!-- Area of Work -->

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="Area_Work">

                            <i class="fas fa-briefcase"></i>

                            Area of Work

                        </label>

                        <input
                            class="form-input"
                            type="text"
                            name="Area_Work"
                            id="Area_Work"
                            value="{{ old('Area_Work', $employer->Area_Work) }}"
                            placeholder="e.g. Software, Finance"
                            required>

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
                            value="{{ old('City', $employer->City) }}"
                            placeholder="Enter city"
                            required>

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
                            rows="3"
                            placeholder="Enter full address"
                            required>{{ old('Address', $employer->Address) }}</textarea>

                    </div>

                </div>

            </div>


            <!-- ═════════════════════════════════════════════
                 MODAL FOOTER
            ══════════════════════════════════════════════ -->

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


@endsection


@section('scripts')

<script>
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


    /* ==========================================================
       CLOSE MODAL WHEN CLICKING OUTSIDE
    ========================================================== */

    const editModal = document.getElementById('editModal');

    if (editModal) {

        editModal.addEventListener('click', function(event) {

            if (event.target === editModal) {

                closeModal();

            }

        });

    }


    /* ==========================================================
       ESCAPE KEY
    ========================================================== */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            const modal = document.getElementById('editModal');

            if (
                modal &&
                modal.classList.contains('show')
            ) {

                closeModal();

            }

        }

    });


    /* ==========================================================
       COMPANY AVATAR HOVER
    ========================================================== */

    const heroAvatar =
        document.querySelector('.hero-avatar');

    const companyHoverOverlay =
        document.getElementById('companyHoverOverlay');


    if (heroAvatar && companyHoverOverlay) {

        heroAvatar.addEventListener(
            'mouseenter',
            function() {

                companyHoverOverlay.style.opacity = '1';

                companyHoverOverlay.style.background =
                    'rgba(0,0,0,0.45)';

            }
        );


        heroAvatar.addEventListener(
            'mouseleave',
            function() {

                companyHoverOverlay.style.opacity = '0';

                companyHoverOverlay.style.background =
                    'rgba(0,0,0,0)';

            }
        );

    }
    /* ==========================================================
   COMPANY LOGO PREVIEW
========================================================== */

    const logoInput = document.getElementById('Photo');
    const logoPreview = document.getElementById('logoPreview');
    const logoFileName = document.getElementById('logoFileName');

    if (logoInput) {

        logoInput.addEventListener('change', function() {

            const file = this.files[0];

            if (!file) {
                return;
            }

            /* Validate file type */

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {

                alert(
                    'Please select a JPG, JPEG, PNG or WEBP image.'
                );

                this.value = '';

                return;
            }


            /* Validate file size */

            const maxSize = 2 * 1024 * 1024;

            if (file.size > maxSize) {

                alert(
                    'Company logo must be smaller than 2 MB.'
                );

                this.value = '';

                return;
            }


            /* Update filename */

            if (logoFileName) {

                logoFileName.textContent =
                    file.name;
            }


            /* Preview */

            const reader = new FileReader();

            reader.onload = function(event) {

                logoPreview.innerHTML = '';

                const img =
                    document.createElement('img');

                img.src = event.target.result;

                img.alt = 'Company Logo Preview';

                logoPreview.appendChild(img);
            };

            reader.readAsDataURL(file);

        });

    }
</script>

@endsection