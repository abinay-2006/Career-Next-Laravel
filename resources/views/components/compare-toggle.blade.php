@props(['job', 'buttonClass' => 'btn-save-job', 'icon' => true])

@php
    /*
     * Add to Compare / In comparison toggle for a job card, a saved job or the
     * job details page. Both endpoints travel with the button because the toggle
     * flips in place without a page reload.
     */
    $compareId = (int) ($job->JobId ?? 0);
    $inCompare = $compareId > 0 && \App\Support\JobComparisonState::contains($compareId);
@endphp

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/job-comparison.css') }}?v={{ @filemtime(public_path('assets/css/job-comparison.css')) ?: 1 }}">
@endpushOnce

@once
    {{-- The toggle needs the lab's behaviour wherever it is used. --}}
    <script src="{{ asset('assets/js/job-comparison.js') }}?v={{ @filemtime(public_path('assets/js/job-comparison.js')) ?: 1 }}" defer></script>
@endonce

<form action="{{ route($inCompare ? 'jobseeker.compare.remove' : 'jobseeker.compare.add', $compareId) }}"
    method="POST" class="cn-cmp-toggle" data-ajax data-no-loader
    data-cmp-toggle
    data-cmp-add="{{ route('jobseeker.compare.add', $compareId) }}"
    data-cmp-remove="{{ route('jobseeker.compare.remove', $compareId) }}"
    data-cmp-job="{{ $compareId }}">
    @csrf

    <button type="submit" class="{{ $buttonClass }} cn-cmp-button{{ $inCompare ? ' in-compare' : '' }}"
        aria-pressed="{{ $inCompare ? 'true' : 'false' }}">
        @if($icon)
        <i class="fas {{ $inCompare ? 'fa-check' : 'fa-scale-balanced' }}" aria-hidden="true"></i>
        @endif
        <span data-cmp-toggle-label>{{ $inCompare ? 'In comparison' : 'Add to Compare' }}</span>
    </button>
</form>
