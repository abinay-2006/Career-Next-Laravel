@extends('layouts.jobseeker')

@section('title', 'Compare Jobs')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/job-comparison.css') }}?v={{ @filemtime(public_path('assets/css/job-comparison.css')) ?: 1 }}">
@endpushOnce

@section('content')
@php
    $columns = $comparison['columns'];
    $highestId = $comparison['highest']['job_id'] ?? null;
    $notDisclosed = \App\Services\JobOfferComparisonService::NOT_DISCLOSED;
    $room = $maxJobs - count($columns);
    $roomNote = $room > 1
        ? 'You have room for ' . $room . ' more jobs.'
        : ($room === 1 ? 'You have room for one more job.' : 'Your comparison is full, so adding one of these asks which job to replace.');
@endphp

<div class="cn-cmp-page">

    <section class="cn-cmp-hero">
        <h1>Job Offer Comparison Lab</h1>
        <p>Compare opportunities and discover which job best fits your career priorities.</p>

        @if($columns !== [])
        <div class="cn-cmp-count" role="status">
            <i class="fas fa-scale-balanced" aria-hidden="true"></i>
            {{ count($columns) }} of {{ $maxJobs }} jobs in your comparison
        </div>

        <div class="cn-cmp-hero-actions">
            <button type="button" class="cn-cmp-btn cn-cmp-btn-ghost cn-cmp-no-print" data-cmp-print>
                <i class="fas fa-print" aria-hidden="true"></i>
                Print comparison
            </button>

            <button type="button" class="cn-cmp-btn cn-cmp-btn-ghost cn-cmp-no-print" data-cmp-copy
                data-cmp-link="{{ route('jobseeker.compare.index', ['jobs' => $shareIds]) }}">
                <i class="fas fa-link" aria-hidden="true"></i>
                Copy comparison link
            </button>

            <form action="{{ route('jobseeker.compare.clear') }}" method="POST" class="cn-cmp-no-print"
                data-ajax data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
                @csrf
                <button type="submit" class="cn-cmp-btn cn-cmp-btn-quiet">
                    <i class="fas fa-eraser" aria-hidden="true"></i>
                    Clear comparison
                </button>
            </form>
        </div>
        @endif
    </section>

    @if($columns === [])

    <section class="cn-cmp-card cn-cmp-empty">
        <div class="cn-cmp-empty-icon">
            <i class="fas fa-table-columns" aria-hidden="true"></i>
        </div>
        <h2>Nothing to compare yet</h2>
        <p>
            Add up to four jobs with <strong>Add to Compare</strong> on any job card, on a saved
            job, or at the top of a job's details page. They line up here side by side, and you
            choose which parts of a job matter most to you.
        </p>
        <div class="cn-cmp-empty-actions">
            <a href="{{ route('jobseeker.jobs') }}" class="cn-cmp-btn">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                Browse jobs
            </a>
            <a href="{{ route('jobseeker.saved-jobs.index') }}" class="cn-cmp-btn cn-cmp-btn-quiet">
                <i class="fas fa-bookmark" aria-hidden="true"></i>
                Open saved jobs
            </a>
        </div>
    </section>

    @else

    {{-- ═══════════════════ PERSONAL PRIORITIES ═══════════════════ --}}
    <section class="cn-cmp-card" aria-labelledby="cnCmpPrioritiesTitle">
        <div class="cn-cmp-card-head">
            <div>
                <h2 id="cnCmpPrioritiesTitle">Your career priorities</h2>
                <p class="cn-cmp-note">Set each factor's importance from 0–100. Career Next automatically converts your preferences into percentage weights used to rank jobs.</p>
            </div>

            <button type="button" class="cn-cmp-btn cn-cmp-btn-quiet cn-cmp-no-print" data-cmp-reset>
                <i class="fas fa-rotate-left" aria-hidden="true"></i>
                Reset preferences
            </button>
        </div>

        <form action="{{ route('jobseeker.compare.weights') }}" method="POST"
            class="cn-cmp-sliders" data-cmp-weights-form data-ajax data-no-loader>
            @csrf
            <input type="hidden" name="reset" value="0">

            @foreach($comparison['factors'] as $factorKey => $factorLabel)
            @php($state = $comparison['availability'][$factorKey])
            <div class="cn-cmp-slider{{ $state['available'] ? '' : ' cn-cmp-slider-off' }}">
                <div class="cn-cmp-slider-top">
                    <label for="cnCmpWeight_{{ $factorKey }}">{{ $factorLabel }}</label>

                    <span class="cn-cmp-slider-read">
                        <span class="cn-cmp-slider-metric">
                            <span class="cn-cmp-slider-metric-name">Importance</span>
                            <output id="cnCmpOut_{{ $factorKey }}" for="cnCmpWeight_{{ $factorKey }}"
                                data-cmp-weight-value="{{ $factorKey }}">{{ $comparison['weights'][$factorKey] }}</output>
                        </span>

                        <span class="cn-cmp-slider-metric">
                            <span class="cn-cmp-slider-metric-name">Comparison Weight</span>
                            <span class="cn-cmp-slider-share" data-cmp-weight-share="{{ $factorKey }}">
                                {{ $comparison['shares'][$factorKey] }}%
                            </span>
                        </span>
                    </span>
                </div>

                <input type="range" id="cnCmpWeight_{{ $factorKey }}" name="{{ $factorKey }}"
                    min="0" max="100" step="5" value="{{ $comparison['weights'][$factorKey] }}"
                    aria-describedby="cnCmpOut_{{ $factorKey }}{{ $state['reason'] !== null || $state['partial'] ? ' cnCmpNote_' . $factorKey : '' }}"
                    data-cmp-weight>

                {{-- How much say this factor currently carries next to the others. --}}
                <span class="cn-cmp-slider-rail" aria-hidden="true">
                    <span data-cmp-weight-bar="{{ $factorKey }}" style="width: {{ $comparison['shares'][$factorKey] }}%;"></span>
                </span>

                @if($state['reason'] !== null)
                <p class="cn-cmp-slider-state" id="cnCmpNote_{{ $factorKey }}"><i class="fas fa-circle-minus" aria-hidden="true"></i> {{ $state['reason'] }} It holds no weight in this comparison.</p>
                @elseif($state['partial'])
                <p class="cn-cmp-slider-state" id="cnCmpNote_{{ $factorKey }}"><i class="fas fa-circle-half-stroke" aria-hidden="true"></i> {{ $state['compared'] - $state['disclosed_by'] }} of {{ $state['compared'] }} listings leave this out, so its weight moves onto the factors those jobs do answer.</p>
                @endif
            </div>
            @endforeach
        </form>

        @if(!$comparison['locations_saved'])
        <p class="cn-cmp-hint">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            Location can only be judged once you save preferred locations under
            <a href="{{ route('jobseeker.preferences.edit') }}">Career Preferences</a>.
        </p>
        @endif

        @if(!$comparison['salary_comparable'])
        <p class="cn-cmp-hint">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            Salary needs at least two listings that state a readable amount before it can be ranked.
        </p>
        @endif
    </section>

    {{-- Outside the sideways scroller, so the titles stay in view on phones. --}}
    <div class="cn-cmp-legend">
        @foreach($columns as $jobId => $column)
        <span class="cn-cmp-legend-chip">
            <b>{{ $loop->iteration }}</b>{{ $column['rows']['job_title']['display'] }}
        </span>
        @endforeach
    </div>

    <p class="cn-cmp-scrollhint" data-cmp-scrollhint>
        <i class="fas fa-arrows-left-right" aria-hidden="true"></i>
        Scroll sideways to see every job
    </p>

    {{-- ═══════════════════ SIDE BY SIDE ═══════════════════ --}}
    <section class="cn-cmp-card" aria-label="Side by side comparison">
        <div class="cn-cmp-scroller" tabindex="0" role="region"
            aria-label="Comparison table. Each column is a job, each row a criterion.">
            <table class="cn-cmp-table">
                <caption class="visually-hidden">
                    Jobs are shown as columns and comparison criteria as rows.
                </caption>

                <thead>
                    <tr>
                        <th scope="col" class="cn-cmp-crit">Job</th>

                        @foreach($columns as $jobId => $column)
                        <th scope="col" class="cn-cmp-col{{ $highestId === (int) $jobId ? ' is-best' : '' }}"
                            data-cmp-column="{{ $jobId }}">
                            <div class="cn-cmp-col-head">
                                <div class="cn-cmp-col-top">
                                    <span class="cn-cmp-col-no">{{ $loop->iteration }}</span>

                                    @if(count($columns) > 1)
                                    <span class="cn-cmp-col-move">
                                        <form action="{{ route('jobseeker.compare.move', $jobId) }}" method="POST"
                                            class="cn-cmp-inline" data-ajax
                                            data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
                                            @csrf
                                            <input type="hidden" name="direction" value="left">
                                            <button type="submit" class="cn-cmp-icon-btn"
                                                aria-label="Move job {{ $loop->iteration }} left"
                                                @if($loop->first) disabled @endif>
                                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                            </button>
                                        </form>

                                        <form action="{{ route('jobseeker.compare.move', $jobId) }}" method="POST"
                                            class="cn-cmp-inline" data-ajax
                                            data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
                                            @csrf
                                            <input type="hidden" name="direction" value="right">
                                            <button type="submit" class="cn-cmp-icon-btn"
                                                aria-label="Move job {{ $loop->iteration }} right"
                                                @if($loop->last) disabled @endif>
                                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </span>
                                    @endif

                                    <form action="{{ route('jobseeker.compare.remove', $jobId) }}" method="POST"
                                        class="cn-cmp-inline" data-ajax
                                        data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
                                        @csrf
                                        <button type="submit" class="cn-cmp-icon-btn"
                                            aria-label="Remove {{ $column['rows']['job_title']['display'] }} from comparison">
                                            <i class="fas fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>

                                <a class="cn-cmp-col-title" href="{{ route('jobseeker.jobs.show', $jobId) }}">
                                    {{ $column['rows']['job_title']['display'] }}
                                </a>
                                <span class="cn-cmp-col-company">{{ $column['rows']['company']['display'] }}</span>

                                <span class="cn-cmp-score" data-cmp-score="{{ $jobId }}">
                                    @if($column['score']['personalized'] !== null)
                                    <b>{{ $column['score']['personalized'] }}</b><small>of 100</small>
                                    @else
                                    <small>{{ $column['score']['status'] === 'no_priorities' ? 'No priorities set' : 'Not enough data' }}</small>
                                    @endif
                                </span>

                                <span class="cn-cmp-disclosed">
                                    {{ $column['disclosed']['count'] }} details disclosed
                                    @if($column['disclosed']['missing'] !== [])
                                    · {{ count($column['disclosed']['missing']) }} missing
                                    @endif
                                </span>
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($comparison['groups'] as $groupLabel => $groupRows)
                    <tr class="cn-cmp-group">
                        <th scope="colgroup" colspan="{{ count($columns) + 1 }}">{{ $groupLabel }}</th>
                    </tr>

                    @foreach($groupRows as $rowKey => $rowLabel)
                    <tr>
                        <th scope="row" class="cn-cmp-crit">{{ $rowLabel }}</th>

                        @foreach($columns as $jobId => $column)
                        @php($cell = $column['rows'][$rowKey])
                        <td class="cn-cmp-cell{{ $cell['missing'] ? ' is-missing' : '' }}">
                            @if($cell['items'] !== [])
                            <span class="cn-cmp-chips">
                                @foreach($cell['items'] as $item)
                                <span class="cn-cmp-chip">{{ $item }}</span>
                                @endforeach
                            </span>
                            @else
                            <span>{{ $cell['display'] }}</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endforeach

                    <tr class="cn-cmp-group">
                        <th scope="colgroup" colspan="{{ count($columns) + 1 }}">Scored against your priorities</th>
                    </tr>

                    @foreach($comparison['factors'] as $factorKey => $factorLabel)
                    <tr>
                        <th scope="row" class="cn-cmp-crit">
                            {{ $factorLabel }}
                            <span class="cn-cmp-crit-weight" data-cmp-weight-share-label="{{ $factorKey }}">
                                {{ $comparison['shares'][$factorKey] }}% weight
                            </span>
                        </th>

                        @foreach($columns as $jobId => $column)
                        @php($factor = $column['factors'][$factorKey])
                        <td class="cn-cmp-cell{{ $factor['available'] ? '' : ' is-missing' }}">
                            @if($factor['available'])
                            <span class="cn-cmp-factor-score">{{ $factor['score'] }}</span>
                            <span class="cn-cmp-factor-basis">{{ $factor['basis'] }}</span>
                            @else
                            <span>{{ $factor['basis'] }}</span>
                            <span class="cn-cmp-factor-left">Left out of this job's score</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach

                    <tr class="cn-cmp-total">
                        <th scope="row" class="cn-cmp-crit">Personalized score</th>

                        @foreach($columns as $jobId => $column)
                        <td class="cn-cmp-cell" data-cmp-total="{{ $jobId }}">
                            @if($column['score']['personalized'] !== null)
                            <strong>{{ $column['score']['personalized'] }}</strong>
                            <span class="cn-cmp-used">
                                @foreach($column['score']['points'] as $usedKey => $point)
                                @if($point['share'] > 0)
                                <span data-cmp-used="{{ $usedKey }}-{{ $jobId }}">
                                    {{ $comparison['factors'][$usedKey] }} {{ $point['share'] }}%
                                </span>
                                @endif
                                @endforeach
                            </span>
                            @else
                            <span class="cn-cmp-unrated">{{ $column['score']['message'] }}</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="cn-cmp-footnote">
            “{{ $notDisclosed }}” means the employer did not state it. Such a factor takes no part in
            that job's score and its share is spread across the factors the job does answer, so
            silence never counts against a listing. Benefits, salary frequency, company growth and
            job quality are not fields this application stores, so nothing is invented for them.
        </p>
    </section>

    {{-- ═══════════════════ RESULTS ═══════════════════ --}}
    <section class="cn-cmp-card cn-cmp-results">
        <h2>What this comparison says</h2>

        <p class="cn-cmp-caveat">
            These rankings follow the priorities you set above. They do not claim that any job here
            is objectively the best one.
        </p>

        <div aria-live="polite">
            <p class="cn-cmp-insufficient" data-cmp-message @if($comparison['message'] === null) hidden @endif>
                {{ $comparison['message'] }}
            </p>

            <div class="cn-cmp-winner" data-cmp-winner @if($highestId === null) hidden @endif>
                <span class="cn-cmp-winner-tag">Best match for your current priorities</span>
                <a class="cn-cmp-winner-title" href="#" data-cmp-winner-link>
                    {{ $comparison['highest']['label'] ?? '' }}
                </a>
                <p class="cn-cmp-explain" data-cmp-explanation>{{ $comparison['explanation'] }}</p>
            </div>
        </div>

        <ul class="cn-cmp-highlights">
            @foreach([
                'salary' => 'Best salary opportunity',
                'skill_match' => 'Best skill compatibility',
                'location' => 'Best location preference',
                'completeness' => 'Most complete job information',
            ] as $highlightKey => $highlightLabel)
            @php($bestJob = $comparison['highlights'][$highlightKey] !== null
                ? ($columns[$comparison['highlights'][$highlightKey]] ?? null)
                : null)
            <li data-cmp-highlight="{{ $highlightKey }}"
                data-cmp-highlight-job="{{ $comparison['highlights'][$highlightKey] }}">
                <span class="cn-cmp-highlight-label">{{ $highlightLabel }}</span>
                <span class="cn-cmp-highlight-job">
                    {{ $bestJob ? $bestJob['rows']['job_title']['display'] : 'Not enough information to compare' }}
                </span>
            </li>
            @endforeach
        </ul>

        <div class="cn-cmp-missing">
            <h3>Information still missing</h3>

            <ul class="cn-cmp-missing-list">
                @foreach($columns as $jobId => $column)
                <li>
                    <b>{{ $loop->iteration }}. {{ $column['rows']['job_title']['display'] }}</b>
                    @if($column['disclosed']['missing'] === [])
                    <span class="cn-cmp-none">Every listed field is disclosed.</span>
                    @else
                    <span class="cn-cmp-chips">
                        @foreach($column['disclosed']['missing'] as $missingLabel)
                        <span class="cn-cmp-chip cn-cmp-chip-gap">{{ $missingLabel }}</span>
                        @endforeach
                    </span>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
    </section>

    <p class="cn-cmp-method">
        Personalized score = the sum of each factor's score multiplied by that factor's share of your
        priorities, over the factors this listing answers. Skill compatibility is the existing Smart
        Job Match percentage, used as it stands.
    </p>

    @if($savedExtras->isNotEmpty())
    <section class="cn-cmp-card cn-cmp-pool" aria-labelledby="cnCmpPoolTitle">
        <h2 id="cnCmpPoolTitle">Add from your saved jobs</h2>
        <p class="cn-cmp-note">{{ $roomNote }}</p>

        <ul class="cn-cmp-pool-list">
            @foreach($savedExtras as $extra)
            <li>
                <span class="cn-cmp-pool-text">
                    <span class="cn-cmp-pool-title">{{ $extra->JobTitle }}</span>
                    <span class="cn-cmp-pool-company">{{ $extra->CompanyName }}</span>
                </span>
                <x-compare-toggle :job="$extra" button-class="cn-cmp-btn cn-cmp-btn-sm" />
            </li>
            @endforeach
        </ul>
    </section>
    @endif

    <x-compare-tray />

    @endif

</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/job-comparison.js') }}?v={{ @filemtime(public_path('assets/js/job-comparison.js')) ?: 1 }}"></script>
@endsection
