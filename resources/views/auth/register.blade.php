@extends('layouts.auth')

@section('title', 'Create Account')

@section('content')

<div class="page-wrapper">

    <!-- LEFT PANEL -->

    <div class="left-panel">

        <div class="brand">

            <h1 class="brand-logo">

                <img
                    src="{{ asset('assets/images/CareerNext.png') }}"
                    alt="Career Next"
                >

                <span>Career Next</span>

            </h1>

        </div>


        <div class="left-features">

            <h2>
                Build your future with Career Next
            </h2>

            <div class="feature-item">

                <div class="dot">
                    <i class="fas fa-search"></i>
                </div>

                <span>
                    Discover opportunities that match your skills
                </span>

            </div>


            <div class="feature-item">

                <div class="dot">
                    <i class="fas fa-bolt"></i>
                </div>

                <span>
                    Apply to jobs quickly and easily
                </span>

            </div>


            <div class="feature-item">

                <div class="dot">
                    <i class="fas fa-user-shield"></i>
                </div>

                <span>
                    Build a secure professional profile
                </span>

            </div>

        </div>


        <div class="left-stats">

            <h2>
                Your Gateway to Professional Success
            </h2>

        </div>

    </div>


    <!-- RIGHT PANEL -->

    <div class="right-panel">

        <div class="panel active">

            


            <div class="panel-title">
                Create your account ✨
            </div>

            <div class="panel-subtitle">
                First, tell us how you want to use Career Next
            </div>


            @if($errors->any())

                <div class="alert error">

                    <i class="fas fa-circle-exclamation"></i>

                    <div>

                        @foreach($errors->all() as $error)

                            <div>
                                {{ $error }}
                            </div>

                        @endforeach

                    </div>

                </div>

            @endif


            <form
                method="POST"
                action="{{ route('register.account-type') }}"
                id="account-type-form"
            >

                @csrf


                <div class="account-type-options">


                    <!-- JOB SEEKER -->

                    <button
                        type="button"
                        class="account-type-card"
                        data-type="JobSeeker"
                    >

                        <div class="account-type-icon jobseeker-icon">

                            <i class="fas fa-user-tie"></i>

                        </div>


                        <div class="account-type-content">

                            <h3>
                                Job Seeker
                            </h3>

                            <p>
                                I'm looking for a job and new career opportunities.
                            </p>


                            <div class="account-type-features">

                                <span>
                                    <i class="fas fa-check"></i>
                                    Find jobs
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Apply easily
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Build your profile
                                </span>

                            </div>

                        </div>


                        <div class="account-type-check">

                            <i class="fas fa-check"></i>

                        </div>

                    </button>


                    <!-- EMPLOYER -->

                    <button
                        type="button"
                        class="account-type-card"
                        data-type="Employer"
                    >

                        <div class="account-type-icon employer-icon">

                            <i class="fas fa-building"></i>

                        </div>


                        <div class="account-type-content">

                            <h3>
                                Employer
                            </h3>

                            <p>
                                I'm hiring and looking for talented candidates.
                            </p>


                            <div class="account-type-features">

                                <span>
                                    <i class="fas fa-check"></i>
                                    Post jobs
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Find candidates
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Manage applications
                                </span>

                            </div>

                        </div>


                        <div class="account-type-check">

                            <i class="fas fa-check"></i>

                        </div>

                    </button>

                </div>


                <input
                    type="hidden"
                    name="account_type"
                    id="account-type"
                >


                <div
                    class="selected-account-message"
                    id="selected-account-message"
                >

                    <i class="fas fa-circle-info"></i>

                    <span>
                        Please select an account type to continue.
                    </span>

                </div>


                <button
                    type="submit"
                    class="btn-primary account-continue-btn"
                    id="account-continue"
                    disabled
                >

                    Continue

                    <i class="fas fa-arrow-right"></i>

                </button>

            </form>


            <div class="switch-text">

                Already have an account?

                <a href="{{ route('login') }}">
                    Sign in
                </a>

            </div>


            <div class="back-home">

                <a href="{{ url('/') }}">

                    <i class="fas fa-arrow-left"></i>

                    Back to Home

                </a>

            </div>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>

document
    .querySelectorAll('.account-type-card')
    .forEach(card => {

        card.addEventListener('click', function () {

            const type = this.dataset.type;


            /*
            |--------------------------------------------------------------------------
            | Remove previous selection
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll('.account-type-card')
                .forEach(item => {

                    item.classList.remove('selected');

                });


            /*
            |--------------------------------------------------------------------------
            | Select current card
            |--------------------------------------------------------------------------
            */

            this.classList.add('selected');


            /*
            |--------------------------------------------------------------------------
            | Store account type
            |--------------------------------------------------------------------------
            */

            document.getElementById('account-type').value = type;


            /*
            |--------------------------------------------------------------------------
            | Update message
            |--------------------------------------------------------------------------
            */

            const message =
                document.getElementById(
                    'selected-account-message'
                );


            if (type === 'JobSeeker') {

                message.innerHTML =
                    '<i class="fas fa-circle-info"></i>' +
                    '<span>You are registering as a Job Seeker.</span>';

            } else {

                message.innerHTML =
                    '<i class="fas fa-circle-info"></i>' +
                    '<span>You are registering as an Employer.</span>';

            }


            /*
            |--------------------------------------------------------------------------
            | Enable continue button
            |--------------------------------------------------------------------------
            */

            document.getElementById(
                'account-continue'
            ).disabled = false;

        });

    });

</script>

@endsection