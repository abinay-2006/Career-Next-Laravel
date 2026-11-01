@php
    $data = $content[$sectionKey] ?? null;
    $sectionEnabled = in_array($sectionKey, $content['sections'] ?? [], true);
    $isList = is_array($data);
    $hasItems = $isList ? collect($data)->contains(fn ($row) => is_array($row) && collect($row)->contains(fn ($value) => filled($value))) : filled($data);
@endphp
@if($sectionEnabled && $hasItems)
<section class="resume-section resume-section-{{ $sectionKey }}">
    <h2>{{ $sectionTitle }}</h2>
    @if($isList)
        @foreach($data as $item)
            @if(is_array($item) && collect($item)->contains(fn ($value) => filled($value)))
                <div class="resume-item">
                    @if($sectionKey === 'education')
                        <div class="resume-item-title">{{ $item['qualification'] ?? '' }}</div>
                        <div class="resume-item-meta">{{ collect([$item['institution'] ?? '', $item['board'] ?? '', $item['year'] ?? '', $item['result'] ?? ''])->filter()->implode(' · ') }}</div>
                    @elseif($sectionKey === 'experience')
                        <div class="resume-item-title">{{ $item['title'] ?? '' }}</div>
                        <div class="resume-item-meta">{{ collect([$item['company'] ?? '', $item['duration'] ?? ''])->filter()->implode(' · ') }}</div>
                        @if(filled($item['responsibilities'] ?? null))<div class="resume-item-text">{{ $item['responsibilities'] }}</div>@endif
                    @elseif($sectionKey === 'certifications')
                        <div class="resume-item-title">{{ $item['name'] ?? '' }}</div>
                        <div class="resume-item-meta">{{ collect([$item['issuer'] ?? '', $item['year'] ?? ''])->filter()->implode(' · ') }}</div>
                    @elseif($sectionKey === 'projects')
                        <div class="resume-item-title">{{ $item['name'] ?? '' }}</div>
                        @if(filled($item['link'] ?? null))<div class="resume-item-meta">{{ $item['link'] }}</div>@endif
                        @if(filled($item['description'] ?? null))<div class="resume-item-text">{{ $item['description'] }}</div>@endif
                    @endif
                </div>
            @endif
        @endforeach
    @else
        <div class="resume-item-text">{{ $data }}</div>
    @endif
</section>
@endif
