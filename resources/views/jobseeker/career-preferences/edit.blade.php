@extends('layouts.jobseeker')

@section('title', 'Career Preferences')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/career-preferences.css') }}?v={{ @filemtime(public_path('assets/css/career-preferences.css')) ?: 1 }}">
@endpush

@section('content')
@php
/** @var \App\Models\JobSeekerCareerPreference|null $preferences */
$jobTitles = [
'Software Developer', 'Web Developer', 'Frontend Developer', 'Backend Developer',
'Full Stack Developer', 'Mobile App Developer', 'Data Analyst', 'Data Scientist',
'AI / Machine Learning Engineer', 'Cybersecurity Analyst', 'Network Administrator',
'Cloud Engineer', 'DevOps Engineer', 'UI/UX Designer', 'Graphic Designer',
'Digital Marketing Executive', 'SEO Specialist', 'Content Writer', 'Accountant',
'Finance Executive', 'HR Executive', 'Business Analyst', 'Sales Executive',
'Customer Support Executive', 'Teacher / Lecturer', 'Civil Engineer',
'Mechanical Engineer', 'Electrical Engineer', 'Healthcare Professional',
'Operations Executive', 'Project Manager', 'Administrative Assistant',
'Internship / Trainee',
];
$locations = [
'Anywhere in India', 'Remote only', 'International opportunities',
'Andhra Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
'Maharashtra', 'Odisha', 'Punjab', 'Rajasthan', 'Tamil Nadu', 'Telangana',
'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
'Delhi', 'Mumbai', 'Bengaluru', 'Hyderabad', 'Chennai', 'Pune', 'Kolkata',
'Ahmedabad', 'Jaipur', 'Lucknow', 'Kochi', 'Chandigarh', 'Indore', 'Nagpur',
'Coimbatore', 'Visakhapatnam', 'Surat', 'Vadodara', 'Noida', 'Gurugram',
];
$industries = [
'Information Technology', 'Software and Technology', 'Banking and Finance',
'Healthcare', 'Education', 'Manufacturing', 'Construction', 'Retail',
'E-commerce', 'Telecommunications', 'Media and Entertainment', 'Hospitality',
'Logistics and Transportation', 'Automobile', 'Government', 'Consulting',
'Agriculture', 'Marketing and Advertising', 'Real Estate', 'Energy', 'Other',
];
$tagValues = static fn (string $key): string => json_encode(array_values($preferences?->{$key} ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div class="main-content cn-career-prefs">

    <div class="cn-prefs-hero">
        <h1><i class="fas fa-sliders" aria-hidden="true"></i> Career Preferences</h1>
        <p>Tell Career Next what kind of employment you're interested in. Everything here is
            optional — save only what you like, and update it any time.</p>
    </div>

    <form method="POST"
        action="{{ route('jobseeker.preferences.update') }}"
        data-cn-career-preferences
        novalidate>
        @csrf
        @method('PUT')

        {{-- A. Job titles --}}
        <section class="cn-prefs-card">
            <h2><i class="fas fa-magnifying-glass-briefcase" aria-hidden="true"></i> What jobs are you interested in?</h2>
            <p class="cn-prefs-hint">Search the list or type your own job titles. Start with this one section if you're short on time.</p>
            <select id="prefs-job-titles" name="preferred_job_titles[]" multiple
                data-cn-tags="{{ $tagValues('preferred_job_titles') }}"
                data-cn-options="{{ json_encode($jobTitles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                aria-label="Preferred job titles">
                @foreach(array_unique(array_merge($jobTitles, $preferences?->preferred_job_titles ?? []), SORT_STRING | SORT_FLAG_CASE) as $t)
                <option value="{{ $t }}" @selected(in_array($t, $preferences?->preferred_job_titles ?? [], true))>{{ $t }}</option>
                @endforeach
            </select>
        </section>

        {{-- B. Locations --}}
        <section class="cn-prefs-card">
            <h2><i class="fas fa-location-dot" aria-hidden="true"></i> Preferred locations</h2>
            <p class="cn-prefs-hint">Pick states, districts or cities — or add your own. You can also choose
                <em>Anywhere in India</em>, <em>International opportunities</em> or <em>Remote only</em>.
            </p>
            <select id="prefs-locations" name="preferred_locations[]" multiple
                data-cn-tags="{{ $tagValues('preferred_locations') }}"
                data-cn-options="{{ json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                aria-label="Preferred locations">
                @foreach(array_unique(array_merge($locations, $preferences?->preferred_locations ?? []), SORT_STRING | SORT_FLAG_CASE) as $t)
                <option value="{{ $t }}" @selected(in_array($t, $preferences?->preferred_locations ?? [], true))>{{ $t }}</option>
                @endforeach
            </select>
        </section>

        {{-- C + D + E: compact grid --}}
        <div class="cn-prefs-grid">
            <section class="cn-prefs-card">
                <h2><i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Expected salary</h2>
                <p class="cn-prefs-hint">Optional. Leave either value empty if you prefer not to say.</p>
                <div class="cn-prefs-salary-row">
                    <div class="cn-prefs-field">
                        <label for="prefs-min-salary">Minimum</label>
                        <input type="number" id="prefs-min-salary" name="minimum_salary" min="0" step="500"
                            class="form-control" placeholder="e.g. 25000"
                            value="{{ old('minimum_salary', $preferences?->minimum_salary !== null ? (float) $preferences->minimum_salary : '') }}">
                    </div>
                    <div class="cn-prefs-field">
                        <label for="prefs-max-salary">Maximum</label>
                        <input type="number" id="prefs-max-salary" name="maximum_salary" min="0" step="500"
                            class="form-control" placeholder="e.g. 45000"
                            value="{{ old('maximum_salary', $preferences?->maximum_salary !== null ? (float) $preferences->maximum_salary : '') }}">
                    </div>
                </div>
                <div class="cn-prefs-salary-row">
                    <div class="cn-prefs-field">
                        <label for="prefs-salary-period">Period</label>
                        <select id="prefs-salary-period" name="salary_period" class="form-select">
                            <option value="">—</option>
                            @foreach(['monthly' => 'Monthly', 'annual' => 'Annually'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('salary_period', $preferences?->salary_period) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cn-prefs-field">
                        <label for="prefs-currency">Currency</label>
                        <select id="prefs-currency" name="currency" class="form-select">
                            @foreach(['INR', 'USD', 'EUR', 'GBP', 'AED'] as $value)
                            <option value="{{ $value }}" @selected(old('currency', $preferences?->currency ?? 'INR') === $value)>{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="cn-prefs-card">
                <h2><i class="fas fa-briefcase" aria-hidden="true"></i> Employment type</h2>
                <p class="cn-prefs-hint">Select all that fit. Custom types are welcome.</p>
                <select id="prefs-employment-types" name="employment_types[]" multiple
                    data-cn-tags="{{ $tagValues('employment_types') }}"
                    data-cn-options="{{ json_encode($suggestions['employment_types'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    aria-label="Preferred employment types">
                    @foreach(array_unique(array_merge($suggestions['employment_types'], $preferences?->employment_types ?? []), SORT_STRING | SORT_FLAG_CASE) as $t)
                    <option value="{{ $t }}" @selected(in_array($t, $preferences?->employment_types ?? [], true))>{{ $t }}</option>
                    @endforeach
                </select>
            </section>

            <section class="cn-prefs-card">
                <h2><i class="fas fa-house-laptop" aria-hidden="true"></i> Work arrangement</h2>
                <p class="cn-prefs-hint">On-site, remote, hybrid — pick as many as you'd consider.</p>
                <select id="prefs-work-arrangements" name="work_arrangements[]" multiple
                    data-cn-tags="{{ $tagValues('work_arrangements') }}"
                    data-cn-options="{{ json_encode($suggestions['work_arrangements'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    aria-label="Preferred work arrangements">
                    @foreach(array_unique(array_merge($suggestions['work_arrangements'], $preferences?->work_arrangements ?? []), SORT_STRING | SORT_FLAG_CASE) as $t)
                    <option value="{{ $t }}" @selected(in_array($t, $preferences?->work_arrangements ?? [], true))>{{ $t }}</option>
                    @endforeach
                </select>
            </section>
        </div>

        {{-- F + G --}}
        <div class="cn-prefs-grid cn-prefs-grid-two">
            <section class="cn-prefs-card">
                <h2><i class="fas fa-city" aria-hidden="true"></i> Industries</h2>
                <p class="cn-prefs-hint">Search or add your own industry.</p>
                <select id="prefs-industries" name="preferred_industries[]" multiple
                    data-cn-tags="{{ $tagValues('preferred_industries') }}"
                    data-cn-options="{{ json_encode($industries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    aria-label="Preferred industries">
                    @foreach(array_unique(array_merge($industries, $preferences?->preferred_industries ?? []), SORT_STRING | SORT_FLAG_CASE) as $t)
                    <option value="{{ $t }}" @selected(in_array($t, $preferences?->preferred_industries ?? [], true))>{{ $t }}</option>
                    @endforeach
                </select>
            </section>

            <section class="cn-prefs-card">
                <h2><i class="fas fa-layer-group" aria-hidden="true"></i> Experience level</h2>
                <p class="cn-prefs-hint">Single choice, optional.</p>
                <select id="prefs-experience-level" name="experience_level" class="form-select">
                    <option value="">Select…</option>
                    @foreach([
                    'fresher' => 'Fresher',
                    'internship' => 'Internship',
                    'entry_level' => 'Entry-level',
                    '1-2_years' => '1–2 years',
                    '3-5_years' => '3–5 years',
                    '6-10_years' => '6–10 years',
                    '10_plus_years' => '10+ years',
                    'any' => 'Any experience level',
                    ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('experience_level', $preferences?->experience_level) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </section>
        </div>

        {{-- H. Additional preferences (collapsible) --}}
        <section class="cn-prefs-card cn-prefs-card-extra">
            <button type="button" class="cn-prefs-extra-toggle" aria-expanded="false" aria-controls="prefs-extra-fields">
                <i class="fas fa-plus" aria-hidden="true"></i>
                Additional preferences (relocation, notice period, hours, urgency)
            </button>
            <div id="prefs-extra-fields" class="cn-prefs-extra-fields" hidden>
                <div class="cn-prefs-grid cn-prefs-grid-four">
                    <div class="cn-prefs-field">
                        <label for="prefs-relocate">Willing to relocate</label>
                        <select id="prefs-relocate" name="willing_to_relocate" class="form-select">
                            <option value="">—</option>
                            @foreach(['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('willing_to_relocate', $preferences?->willing_to_relocate) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cn-prefs-field">
                        <label for="prefs-notice">Notice period</label>
                        <select id="prefs-notice" name="notice_period" class="form-select">
                            <option value="">—</option>
                            @foreach([
                            'immediate' => 'Immediate',
                            '15_days' => '15 days',
                            '30_days' => '30 days',
                            '60_days' => '60 days',
                            '90_days' => '90 days',
                            'other' => 'Other',
                            ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('notice_period', $preferences?->notice_period) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cn-prefs-field">
                        <label for="prefs-hours">Preferred working hours</label>
                        <select id="prefs-hours" name="preferred_working_hours" class="form-select">
                            <option value="">—</option>
                            @foreach(['day' => 'Day', 'night' => 'Night', 'flexible' => 'Flexible'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('preferred_working_hours', $preferences?->preferred_working_hours) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cn-prefs-field">
                        <label for="prefs-urgency">Job urgency</label>
                        <select id="prefs-urgency" name="job_search_status" class="form-select">
                            <option value="">—</option>
                            @foreach([
                            'actively_looking' => 'Actively looking',
                            'open_to_opportunities' => 'Open to opportunities',
                            'just_exploring' => 'Just exploring',
                            ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('job_search_status', $preferences?->job_search_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <div class="cn-prefs-actions">
            <a href="{{ route('jobseeker.index') }}" class="btn cn-prefs-skip">Skip for now</a>
            <button type="submit" class="btn complete-profile-btn cn-prefs-save">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Save preferences
            </button>
        </div>
    </form>

    {{-- Saved on its own route, so it sits outside the preferences form. --}}
    <x-seeker-skills />
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="{{ asset('assets/js/job-requirements.js') }}?v={{ @filemtime(public_path('assets/js/job-requirements.js')) ?: 1 }}"></script>
<script src="{{ asset('assets/js/career-preferences.js') }}?v={{ @filemtime(public_path('assets/js/career-preferences.js')) ?: 1 }}"></script>
@endsection