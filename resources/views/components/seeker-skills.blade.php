@php
    /*
     * Shared by the profile page and the career preferences page: the seeker's
     * own list of skills and software. Reads are table-guarded so a database
     * that has not run the migration yet still renders the rest of the page.
     */
    $skillSeekerId = (int) (auth('jobseeker')->user()->JobSeekId ?? 0);
    $savedSkills = \App\Models\JobSeekerSkill::findFor($skillSeekerId);
    $resumeSkillNames = app(\App\Services\JobMatchService::class)->resumeSkillNames($skillSeekerId);
@endphp

<section class="cn-seeker-skills">

    <div class="cn-seeker-skills-head">
        <h2><i class="fas fa-puzzle-piece" aria-hidden="true"></i> Skills &amp; Software</h2>
        <p class="cn-seeker-skills-intro">
            List the tools and skills you can actually use. Smart Job Match compares this list with each
            listing's requirements, alongside the skills in your saved resume. The level you pick stays on
            your profile — it is not shown to employers as a verified skill.
        </p>
    </div>

    <form method="POST" action="{{ route('jobseeker.profile.skills.update') }}">
        @csrf
        @method('PUT')

        <div class="cn-seeker-skills-grid">

            <div class="cn-seeker-skills-field">
                <div class="cn-seeker-skills-label">
                    <i class="fas fa-code" aria-hidden="true"></i> Skills you know
                </div>

                <div class="cn-req-picker" id="cnSkillPicker"
                    data-cn-req-picker="skills" data-cn-req-kind="skills" data-cn-req-types="off">
                    <select class="js-req-select" multiple aria-label="Skills you know"></select>
                    <div class="cn-req-rows js-req-rows"></div>
                    <input type="hidden" name="skills" value="{{ old('skills', json_encode($savedSkills?->skills ?? [])) }}">
                </div>

                <span class="cn-req-error" data-cn-error-for="skills">@error('skills'){{ $message }}@enderror</span>
            </div>

            <div class="cn-seeker-skills-field">
                <div class="cn-seeker-skills-label">
                    <i class="fas fa-laptop-code" aria-hidden="true"></i> Software you know
                </div>

                <div class="cn-req-picker" id="cnSoftwarePicker"
                    data-cn-req-picker="software" data-cn-req-kind="software" data-cn-req-types="off">
                    <select class="js-req-select" multiple aria-label="Software you know"></select>
                    <div class="cn-req-rows js-req-rows"></div>
                    <input type="hidden" name="software" value="{{ old('software', json_encode($savedSkills?->software ?? [])) }}">
                </div>

                <span class="cn-req-error" data-cn-error-for="software">@error('software'){{ $message }}@enderror</span>
            </div>

        </div>

        <div class="cn-seeker-skills-actions">
            @if ($resumeSkillNames !== [])
            <button type="button" class="btn cn-seeker-skills-copy"
                onclick="CareerNextRequirementPickers.add('#cnSkillPicker', {{ json_encode($resumeSkillNames) }})">
                <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                Copy skills from my resume
            </button>
            @else
            <span class="cn-seeker-skills-note">
                Save a resume in Resume Builder and its skills can be copied here in one click.
            </span>
            @endif

            <button type="submit" class="btn complete-profile-btn cn-seeker-skills-save">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Save skills
            </button>
        </div>
    </form>

</section>
