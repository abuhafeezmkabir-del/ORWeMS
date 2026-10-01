@props([
    'title' => null,
    'subtitle' => null,
    'footer' => null,
    'headerClass' => '',
    'bodyClass' => '',
])

<div {{ $attributes->merge(['class' => 'card shadow-sm border-0']) }}>
    @if ($title || isset($header))
        <div class="card-header bg-white border-bottom py-3 {{ $headerClass }}">
            @if (isset($header))
                {{ $header }}
            @else
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title fw-bold mb-0 text-dark">{{ $title }}</h5>
                        @if ($subtitle)
                            <p class="card-subtitle text-muted small mt-1 mb-0">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @if ($footer || isset($cardFooter))
        <div class="card-footer bg-white border-top text-muted small py-2">
            {{ $cardFooter ?? $footer }}
        </div>
    @endif
</div>
