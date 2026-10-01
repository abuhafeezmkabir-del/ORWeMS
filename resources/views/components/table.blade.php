@props([
    'responsive' => true,
    'striped' => true,
    'hover' => true,
    'small' => false,
])

@php
    $tableClasses = 'table mb-0 align-middle';
    if ($striped) $tableClasses .= ' table-striped';
    if ($hover) $tableClasses .= ' table-hover';
    if ($small) $tableClasses .= ' table-sm';
@endphp

@if ($responsive)
    <div class="table-responsive">
        <table {{ $attributes->merge(['class' => $tableClasses]) }}>
            {{ $slot }}
        </table>
    </div>
@else
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        {{ $slot }}
    </table>
@endif
