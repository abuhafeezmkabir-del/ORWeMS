@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'd-flex flex-column flex-md-row align-items-md-center justify-content-between pb-3 mb-3 border-bottom gap-3']) }}>
    <div>
        @if (!empty($breadcrumbs))
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    @foreach ($breadcrumbs as $crumbLabel => $crumbUrl)
                        @if ($loop->last || is_null($crumbUrl))
                            <li class="breadcrumb-item active" aria-current="page">{{ $crumbLabel }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $crumbUrl }}" class="text-decoration-none">{{ $crumbLabel }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($title)
            <h1 class="h3 fw-bold text-dark mb-0">{{ $title }}</h1>
        @endif

        @if ($subtitle)
            <p class="text-muted small mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="d-flex align-items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
