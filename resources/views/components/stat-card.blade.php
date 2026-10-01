@props([
    'label',
    'value' => '—',
    'icon' => 'bi-folder',
    'variant' => 'primary',
    'hint' => null,
])

@php
    $bgClass = match ($variant) {
        'primary' => 'bg-primary-subtle text-primary',
        'success' => 'bg-success-subtle text-success',
        'warning' => 'bg-warning-subtle text-warning-emphasis',
        'danger' => 'bg-danger-subtle text-danger',
        'info' => 'bg-info-subtle text-info-emphasis',
        default => 'bg-secondary-subtle text-secondary',
    };
@endphp

<div class="stat-card">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="stat-card-label">{{ $label }}</span>
        <div class="stat-card-icon {{ $bgClass }}">
            <i class="bi {{ $icon }}"></i>
        </div>
    </div>
    <div class="stat-card-value">{{ $value }}</div>
    @if ($hint)
        <div class="small text-muted mt-2 d-flex align-items-center gap-1">
            <i class="bi bi-info-circle"></i>
            <span>{{ $hint }}</span>
        </div>
    @endif
</div>
