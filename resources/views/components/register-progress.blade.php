@props(['current' => 1])

@php
    $steps = ['Account', 'Details', 'Profile', 'Professional', 'Review', 'Verify'];
    $current = max(1, min(count($steps), (int) $current));
@endphp

<div class="registration-progress" aria-label="Registration progress">
    @foreach($steps as $index => $label)
        @php
            $number = $index + 1;
            $state = $number < $current ? 'completed' : ($number === $current ? 'active' : '');
            $line = $number < $current ? ($number === $current - 1 ? 'active' : 'completed') : '';
        @endphp

        <div class="progress-step{{ $state ? ' ' . $state : '' }}"@if($state === 'active') aria-current="step"@endif>
            <div class="step-number">
                @if($state === 'completed')
                    <i class="fas fa-check" aria-hidden="true"></i>
                @else
                    {{ $number }}
                @endif
            </div>
            <span>{{ $label }}</span>
        </div>

        @if(!$loop->last)
            <div class="progress-line{{ $line ? ' ' . $line : '' }}"></div>
        @endif
    @endforeach
</div>
