@props([
    'type' => 'info',
    'dismissible' => false,
])

@php
    $icons = [
        'primary' => 'bi-info-circle-fill',
        'secondary' => 'bi-info-circle',
        'success' => 'bi-check-circle-fill',
        'danger' => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info' => 'bi-info-circle-fill',
        'light' => 'bi-info-circle',
        'dark' => 'bi-info-circle',
    ];
    $icon = $icons[$type] ?? 'bi-info-circle-fill';
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-' . $type . ' d-flex align-items-start gap-2 ' . ($dismissible ? 'alert-dismissible fade show' : ''), 'role' => 'alert']) }}>
    <i class="bi {{ $icon }} fs-5 flex-shrink-0 mt-n1"></i>
    <div class="flex-grow-1">
        {{ $slot }}
    </div>
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
