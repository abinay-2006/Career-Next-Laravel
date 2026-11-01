@php($content = $content ?? $resume->content)
<div class="resume-document resume-{{ $template }}">
    @if($template === 'modern')
        <header class="resume-header">
            @if($photoData)<img class="resume-photo" src="{{ $photoData }}" alt="Profile photo">@endif
            <h1>{{ $content['name'] ?? '' }}</h1>
            <div class="resume-contact">{{ collect([$content['email'] ?? null, $content['phone'] ?? null, $content['location'] ?? null, isset($content['age']) && $content['age'] !== '' ? 'Age '.$content['age'] : null, $content['gender'] ?? null])->filter()->implode('  ·  ') }}</div>
            @if(filled($content['qualification'] ?? null))<strong class="resume-qualification">{{ $content['qualification'] }}</strong>@endif
        </header>
        <table class="resume-modern-table"><tbody><tr>
            <td class="resume-modern-aside">
                @foreach(['skills'=>'Skills','languages'=>'Languages','certifications'=>'Certifications','achievements'=>'Achievements'] as $key=>$title)
                    @include('jobseeker.resumes.partials.section', ['sectionKey'=>$key,'sectionTitle'=>$title,'content'=>$content])
                @endforeach
            </td>
            <td class="resume-modern-main">
                @foreach(['summary'=>'Professional Summary','experience'=>'Work Experience','education'=>'Education','projects'=>'Projects','internships'=>'Internships'] as $key=>$title)
                    @include('jobseeker.resumes.partials.section', ['sectionKey'=>$key,'sectionTitle'=>$title,'content'=>$content])
                @endforeach
            </td>
        </tr></tbody></table>
    @else
        <header class="resume-header">
            @if($photoData)<img class="resume-photo" src="{{ $photoData }}" alt="Profile photo">@endif
            <h1>{{ $content['name'] ?? '' }}</h1>
            <div class="resume-contact">{{ collect([$content['email'] ?? null, $content['phone'] ?? null, $content['location'] ?? null, isset($content['age']) && $content['age'] !== '' ? 'Age '.$content['age'] : null, $content['gender'] ?? null])->filter()->implode('  ·  ') }}</div>
            @if(filled($content['qualification'] ?? null))<strong class="resume-qualification">{{ $content['qualification'] }}</strong>@endif
        </header>
        @foreach(['summary'=>'Professional Summary','education'=>'Education','experience'=>'Work Experience','skills'=>'Skills','certifications'=>'Certifications','projects'=>'Projects','languages'=>'Languages','achievements'=>'Achievements','internships'=>'Internships'] as $key=>$title)
            @include('jobseeker.resumes.partials.section', ['sectionKey'=>$key,'sectionTitle'=>$title,'content'=>$content])
        @endforeach
    @endif
</div>
