@if (session('success'))
    <x-alert type="success" :dismissible="true">
        {{ session('success') }}
    </x-alert>
@endif

@if (session('error'))
    <x-alert type="danger" :dismissible="true">
        {{ session('error') }}
    </x-alert>
@endif

@if (session('warning'))
    <x-alert type="warning" :dismissible="true">
        {{ session('warning') }}
    </x-alert>
@endif

@if (session('info'))
    <x-alert type="info" :dismissible="true">
        {{ session('info') }}
    </x-alert>
@endif

@if ($errors->any())
    <x-alert type="danger" :dismissible="true">
        <div class="fw-semibold mb-1">Please correct the following errors:</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
