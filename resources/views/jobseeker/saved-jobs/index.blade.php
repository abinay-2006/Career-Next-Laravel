@extends('layouts.jobseeker')

@section('title', 'Saved Jobs')

@section('content')
<style>
    .cn-saved-page {
        padding-bottom: 35px;
        color: var(--cn-text, var(--theme-text-primary, #29283b));
    }

    .cn-saved-hero {
        position: relative;
        overflow: hidden;
        padding: 30px;
        margin-bottom: 25px;
        border-radius: 20px;
        background: linear-gradient(120deg,
                var(--cn-primary, #6750b8),
                var(--cn-primary-hover, #5143a5));
        color: #fff;
        box-shadow: 0 12px 30px rgba(80, 60, 150, .17);
    }

    .cn-saved-hero::after {
        content: "\f02e";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: 35px;
        top: 5px;
        font-size: 110px;
        opacity: .10;
        transform: rotate(-12deg);
        pointer-events: none;
    }

    .cn-saved-hero h1 {
        color: #fff !important;
        font-size: clamp(1.6rem, 3vw, 2rem);
        font-weight: 800;
        margin: 0 0 9px;
    }

    .cn-saved-hero p {
        color: rgba(255, 255, 255, .88) !important;
        margin: 0;
        max-width: 520px;
    }

    .cn-saved-count {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin-top: 20px;
        padding: 9px 15px;
        border-radius: 30px;
        background: rgba(255, 255, 255, .18);
        color: #fff;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, .25);
    }

    .cn-saved-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 310px), 1fr));
        gap: 20px;
    }

    .cn-saved-card {
        display: flex;
        flex-direction: column;
        padding: 22px;
        min-width: 0;
        border-radius: 17px;
        background: var(--cn-surface, var(--theme-card-background, #fff));
        border: 1px solid var(--cn-border, var(--theme-border, #e5e1f0));
        box-shadow: 0 7px 23px rgba(35, 25, 75, .055);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .cn-saved-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 13px 30px rgba(35, 25, 75, .11);
    }

    .cn-saved-card-top {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        margin-bottom: 18px;
    }

    .cn-saved-company-icon {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 13px;
        background: var(--cn-primary-soft, var(--theme-hover, #f0ebff));
        color: var(--cn-primary, #6750b8);
        font-size: 20px;
    }

    .cn-saved-card h3 {
        color: var(--cn-text, var(--theme-text-primary, #29283b));
        font-size: 1.08rem;
        font-weight: 750;
        margin: 0 0 6px;
        overflow-wrap: anywhere;
    }

    .cn-saved-company {
        color: var(--cn-text-secondary, var(--theme-text-secondary, #777));
        font-size: .9rem;
        font-weight: 600;
    }

    .cn-saved-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin-bottom: 15px;
    }

    .cn-saved-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 8px;
        background: var(--cn-surface-secondary, var(--theme-hover, #f2effb));
        color: var(--cn-text-secondary, var(--theme-text-secondary, #67657a));
        font-size: .8rem;
        font-weight: 600;
    }

    .cn-saved-description {
        color: var(--cn-text-secondary, var(--theme-text-secondary, #777));
        font-size: .9rem;
        line-height: 1.65;
        margin: 0 0 18px;
    }

    .cn-saved-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: auto;
        padding-top: 12px;
    }

    .cn-saved-actions form {
        margin: 0;
    }

    .cn-saved-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 10px 15px;
        border-radius: 10px;
        font-size: .88rem;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: .2s ease;
    }

    .cn-saved-view {
        background: var(--cn-primary, #6750b8);
        color: #fff !important;
        border: 1px solid var(--cn-primary, #6750b8);
    }

    .cn-saved-view:hover {
        filter: brightness(.9);
        transform: translateY(-1px);
    }

    .cn-saved-remove {
        background: transparent;
        color: var(--cn-text-secondary, var(--theme-text-secondary, #777));
        border: 1px solid var(--cn-border, var(--theme-border, #ddd));
    }

    .cn-saved-remove:hover {
        background: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }

    .cn-saved-empty {
        text-align: center;
        padding: 55px 20px;
        border-radius: 18px;
        background: var(--cn-surface, var(--theme-card-background, #fff));
        border: 1px solid var(--cn-border, var(--theme-border, #e5e1f0));
    }

    .cn-saved-empty-icon {
        width: 85px;
        height: 85px;
        display: grid;
        place-items: center;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: var(--cn-primary-soft, var(--theme-hover, #f0ebff));
        color: var(--cn-primary, #6750b8);
        font-size: 34px;
    }

    .cn-saved-empty h2 {
        color: var(--cn-text, var(--theme-text-primary, #29283b));
        font-weight: 800;
        font-size: 1.35rem;
    }

    .cn-saved-empty p {
        color: var(--cn-text-secondary, var(--theme-text-secondary, #777));
        margin: 10px auto 22px;
        max-width: 450px;
    }

    .cn-saved-browse {
        display: inline-flex;
        gap: 9px;
        align-items: center;
        justify-content: center;
        padding: 12px 20px;
        background: var(--cn-primary, #6750b8);
        color: #fff !important;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
    }

    .cn-saved-browse:hover {
        filter: brightness(.9);
    }

    @media(max-width:575px) {
        .cn-saved-hero {
            padding: 23px 20px;
        }

        .cn-saved-card {
            padding: 17px;
        }

        .cn-saved-hero::after {
            right: 12px;
            font-size: 75px;
        }

        .cn-saved-actions>* {
            flex: 1;
        }

        .cn-saved-actions .cn-saved-btn {
            width: 100%;
        }
    }
</style>

<div class="main-content cn-saved-page" data-ajax-region>

    <div class="cn-saved-hero">
        <h1><i class="fas fa-bookmark"></i> My Saved Jobs</h1>
        <p>
            Your favourite career opportunities, all in one place.
            Revisit them whenever you are ready to apply.
        </p>

        <div class="cn-saved-count">
            <i class="fas fa-bookmark"></i>
            {{ $savedJobs->total() }}
            {{ $savedJobs->total() === 1 ? 'Job Saved' : 'Jobs Saved' }}
        </div>
    </div>

    @if($savedJobs->count())

    <div class="cn-saved-grid">

        @foreach($savedJobs as $savedJob)
        @php($job = $savedJob->job)

        @if($job)
        <article class="cn-saved-card">

            <div class="cn-saved-card-top">
                <div class="cn-saved-company-icon">
                    <i class="fas fa-building"></i>
                </div>

                <div>
                    <h3>{{ $job->JobTitle }}</h3>
                    <div class="cn-saved-company">
                        {{ $job->CompanyName }}
                    </div>
                </div>
            </div>

            <div class="cn-saved-meta">
                <span>
                    <i class="fas fa-users"></i>
                    {{ $job->Vacancy }} Vacancies
                </span>

                <span>
                    <i class="fas fa-graduation-cap"></i>
                    {{ $job->MinQualification }}
                </span>
            </div>

            @if(strtolower(trim((string) $job->Status)) === 'approved')

            @if($job->Description)
            <p class="cn-saved-description">
                {{ \Illuminate\Support\Str::limit($job->Description, 150) }}
            </p>
            @endif

            <div class="cn-saved-actions">
                <a href="{{ route('jobseeker.jobs.show', $job->JobId) }}"
                    class="cn-saved-btn cn-saved-view">
                    <i class="fas fa-eye"></i>
                    View Details
                </a>

                <form action="{{ route('jobseeker.saved-jobs.destroy', $job->JobId) }}"
                    method="POST"
                    data-ajax
                    data-ajax-refresh-target="[data-ajax-region]">
                    @csrf

                    <button type="submit"
                        class="cn-saved-btn cn-saved-remove">
                        <i class="fas fa-trash-alt"></i>
                        Remove
                    </button>
                </form>
            </div>

            @else

            <p class="cn-saved-description">
                This vacancy is no longer available.
            </p>

            <div class="cn-saved-actions">
                <form action="{{ route('jobseeker.saved-jobs.destroy', $job->JobId) }}"
                    method="POST"
                    data-ajax
                    data-ajax-refresh-target="[data-ajax-region]">
                    @csrf

                    <button type="submit"
                        class="cn-saved-btn cn-saved-remove">
                        <i class="fas fa-trash-alt"></i>
                        Remove Saved Job
                    </button>
                </form>
            </div>

            @endif

        </article>
        @endif
        @endforeach

    </div>

    <div class="mt-4">
        {{ $savedJobs->links() }}
    </div>

    @else

    <div class="cn-saved-empty">
        <div class="cn-saved-empty-icon">
            <i class="far fa-bookmark"></i>
        </div>

        <h2>No Saved Jobs Yet!</h2>

        <p>
            You haven't bookmarked any vacancies.
            Explore available opportunities and save the jobs
            that match your career goals.
        </p>

        <a href="{{ route('jobseeker.jobs') }}" class="cn-saved-browse">
            <i class="fas fa-search"></i>
            Explore Jobs
        </a>
    </div>

    @endif

</div>
@endsection
