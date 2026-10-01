@props([
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
    'icon' => null,
    'href' => null,
    'disabled' => false,
])

@php
    $classes = 'btn btn-' . $variant . ($size ? ' btn-' . $size : '');
@endphp

@if ($href)
    <a href="{{ $disabled ? '#' : $href }}"
       {{ $attributes->merge(['class' => $classes . ($disabled ? ' disabled' : '')]) }}
       @if ($disabled) aria-disabled="true" tabindex="-1" @endif>
        @if ($icon) <i class="bi {{ $icon }} me-1"></i> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->merge(['class' => $classes]) }}
            @disabled($disabled)>
        @if ($icon) <i class="bi {{ $icon }} me-1"></i> @endif
        {{ $slot }}
    </button>
@endif
