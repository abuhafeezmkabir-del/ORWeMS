@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('main')
    <div class="text-center mb-4">
        <i class="bi bi-shield-heart fs-1 text-primary"></i>
        <h1 class="h5 fw-bold mt-2 mb-1">Choose a new password</h1>
        <p class="text-muted small mb-0">
            Use a password you have not used elsewhere. It must be at least 12 characters.
        </p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('password.store') }}" novalidate>
            @csrf
            {{-- The token is carried in the URL, never in the form body, so it
                 cannot be replayed from a cached or prefilled page. --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <x-form-input
                name="email"
                label="Email address"
                type="email"
                :value="$email"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                required
            />

            <x-form-input
                name="password"
                label="New password"
                type="password"
                autocomplete="new-password"
                help="At least 12 characters, including letters and numbers."
                required
                autofocus
            />

            <x-form-input
                name="password_confirmation"
                label="Confirm new password"
                type="password"
                autocomplete="new-password"
                required
            />

            <x-button type="submit" class="w-100">
                <i class="bi bi-shield-check me-1"></i>
                Reset password
            </x-button>
        </form>
    </x-card>
@endsection