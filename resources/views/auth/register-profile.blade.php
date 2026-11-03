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
                        class="cn-logo-image cn-logo-image--auth"
                        src="{{ asset('assets/images/career_next.png') }}"
                        alt="Career Next Logo">
                </span>

                <span>Career Next</span>

            </a>

        </div>


        <!-- =====================================================
             PROGRESS
             ===================================================== -->

        <x-register-progress :current="3" />


        <!-- =====================================================
             CARD
             ===================================================== -->

        <div class="registration-content">


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

                    <label for="gender-select">

                        Gender

                        <span>*</span>

                    </label>

                    <div class="input-wrapper">

                        <i class="fas fa-venus-mars"></i>

                        @php
                            $selectedGender = old('gender', $profile['gender'] ?? '');
                        @endphp

                        <div class="custom-select profile-gender-select" id="gender-select-wrap">
                            <div class="select-trigger" id="gender-select-trigger" role="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="gender-select-listbox" tabindex="0">
                                <span class="select-value" id="gender-select-value">{{ $selectedGender === '' ? 'Select gender' : $selectedGender }}</span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </div>
                            <div class="select-listbox" id="gender-select-listbox" role="listbox" aria-labelledby="gender-select-trigger">
                                <div class="select-option" role="option" data-value="" aria-selected="{{ $selectedGender === '' ? 'true' : 'false' }}">Select gender</div>
                                <div class="select-option" role="option" data-value="Male" aria-selected="{{ $selectedGender === 'Male' ? 'true' : 'false' }}">Male</div>
                                <div class="select-option" role="option" data-value="Female" aria-selected="{{ $selectedGender === 'Female' ? 'true' : 'false' }}">Female</div>
                                <div class="select-option" role="option" data-value="Other" aria-selected="{{ $selectedGender === 'Other' ? 'true' : 'false' }}">Other</div>
                            </div>
                            <input type="hidden" name="gender" id="gender-select" value="{{ $selectedGender }}" required>
                        </div>

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

            window.CareerNextButtonLoading?.start(button, 'Saving...');

        });
</script>

<script>
/*
|--------------------------------------------------------------------------
| Custom gender select (theme-aware listbox)
|--------------------------------------------------------------------------
*/
(function initGenderSelect() {
    "use strict";

    var wrap = document.getElementById("gender-select-wrap");
    var trigger = document.getElementById("gender-select-trigger");
    var valueEl = document.getElementById("gender-select-value");
    var listbox = document.getElementById("gender-select-listbox");
    var hiddenInput = document.getElementById("gender-select");
    var arrow = trigger && trigger.querySelector(".dropdown-arrow");
    var options = Array.prototype.slice.call(
        listbox.querySelectorAll(".select-option")
    );

    if (!wrap || !trigger || !listbox || !hiddenInput || options.length === 0) {
        return;
    }

    var selectedIndex = -1;
    var highlightedIndex = -1;

    function render() {
        var isOpen = wrap.classList.contains("open");
        var label = "Select gender";
        var val = "";

        if (selectedIndex >= 0 && options[selectedIndex]) {
            label = options[selectedIndex].textContent;
            val = options[selectedIndex].getAttribute("data-value") || "";
        }

        valueEl.textContent = label;
        hiddenInput.value = val;
        trigger.setAttribute("aria-expanded", String(isOpen));
        if (arrow) arrow.classList.toggle("open", isOpen);

        options.forEach(function (o, i) {
            o.setAttribute("aria-selected", i === selectedIndex ? "true" : "false");
            o.setAttribute("data-active", i === highlightedIndex ? "true" : "false");
            o.tabIndex = i === highlightedIndex ? 0 : -1;
        });
    }

    function openListbox() {
        if (wrap.classList.contains("open")) {
            return;
        }
        wrap.classList.add("open");
        listbox.style.display = "block";
        highlightedIndex = selectedIndex >= 0 ? selectedIndex : 0;
        render();
        var el = options[highlightedIndex];
        if (el) {
            el.focus();
        }
    }

    function closeListbox() {
        if (!wrap.classList.contains("open")) {
            return;
        }
        wrap.classList.remove("open");
        listbox.style.display = "none";
        highlightedIndex = -1;
        render();
        trigger.focus();
    }

    function selectIndex(index) {
        if (index < 0 || index >= options.length) {
            return;
        }
        selectedIndex = index;
        highlightedIndex = index;
        closeListbox();
    }

    function highlight(index) {
        if (index < 0) {
            index = 0;
        }
        if (index >= options.length) {
            index = options.length - 1;
        }
        highlightedIndex = index;
        render();
        var el = options[index];
        if (el) {
            el.focus();
        }
    }

    trigger.addEventListener("click", function () {
        if (wrap.classList.contains("open")) {
            closeListbox();
        } else {
            openListbox();
        }
    });

    trigger.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            openListbox();
        }
    });

    listbox.addEventListener("keydown", function (e) {
        if (!wrap.classList.contains("open")) {
            return;
        }
        if (e.key === "Escape") {
            e.preventDefault();
            closeListbox();
            return;
        }
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            selectIndex(highlightedIndex);
            return;
        }
        if (e.key === "ArrowDown") {
            e.preventDefault();
            highlight(highlightedIndex + 1);
            return;
        }
        if (e.key === "ArrowUp") {
            e.preventDefault();
            highlight(highlightedIndex - 1);
            return;
        }
        if (e.key === "Home") {
            e.preventDefault();
            highlight(0);
            return;
        }
        if (e.key === "End") {
            e.preventDefault();
            highlight(options.length - 1);
            return;
        }
    });

    options.forEach(function (o, i) {
        o.addEventListener("click", function () {
            selectIndex(i);
        });
        o.addEventListener("mouseenter", function () {
            highlight(i);
        });
    });

    document.addEventListener("click", function (e) {
        if (!wrap.contains(e.target)) {
            closeListbox();
        }
    });

    render();
})();
</script>

@endsection
