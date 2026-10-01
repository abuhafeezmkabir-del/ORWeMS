@props([
    'variant' => 'secondary',
    'pill' => false,
])

@php
    $classes = 'badge bg-' . $variant . ($pill ? ' rounded-pill' : '');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
