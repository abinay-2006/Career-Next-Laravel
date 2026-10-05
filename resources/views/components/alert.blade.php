@props(['type' => 'info', 'message' => '', 'title' => null, 'dismissible' => true, 'duration' => null])

@php
    $alertTypes = [
        'success' => ['icon' => 'fa-circle-check', 'role' => 'status'],
        'error' => ['icon' => 'fa-circle-exclamation', 'role' => 'alert'],
        'warning' => ['icon' => 'fa-triangle-exclamation', 'role' => 'alert'],
        'info' => ['icon' => 'fa-circle-info', 'role' => 'status'],
    ];
    $type = array_key_exists($type, $alertTypes) ? $type : 'info';
@endphp
<div class="cn-alert cn-alert-{{ $type }}" role="{{ $alertTypes[$type]['role'] }}" aria-live="{{ $type === 'error' || $type === 'warning' ? 'assertive' : 'polite' }}" data-alert data-alert-duration="{{ $duration ?? '' }}">
    <i class="fa-solid {{ $alertTypes[$type]['icon'] }} cn-alert-icon" aria-hidden="true"></i>
    <div class="cn-alert-content">
        @if(!empty($title))<strong class="cn-alert-title">{{ $title }}</strong>@endif
        <span class="cn-alert-message">{{ $message }}</span>
    </div>
    @if($dismissible)
        <button type="button" class="cn-alert-close" data-alert-close aria-label="Dismiss {{ $type }} message"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    @endif
</div>
