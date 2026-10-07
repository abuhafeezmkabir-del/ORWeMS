@extends('layouts.guest')

@section('title', 'Change password')

@section('main')
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock fs-1 text-primary"></i>
        <h1 class="h5 fw-bold mt-2 mb-1">Change your password</h1>
        <p class="text-muted small mb-0">
            Set a new password for {{ Auth::user()?->email ?? 'your account' }}.
        </p>
    </div>

    <x-card>
        <x-alert type="info" class="mb-3">
            Changing your password ends <strong>every</strong> session on this
            account, including this one, and you will be asked to sign in again.
        </x-alert>

        @if (session('status'))
            <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf

            <x-form-input
                name="current_password"
                label="Current password"
                type="password"
                autocomplete="current-password"
                required
                autofocus
            />

            <x-form-input
                name="password"
                label="New password"
                type="password"
                autocomplete="new-password"
                help="At least 12 characters, containing letters and numbers."
                required
            />

            <x-form-input
                name="password_confirmation"
                label="Confirm new password"
                type="password"
                autocomplete="new-password"
                required
            />

            <x-button type="submit" class="w-100">
                <i class="bi bi-key me-1"></i>
                Change password
            </x-button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('dashboard') }}" class="small">
                <i class="bi bi-arrow-left me-1"></i>Back to the dashboard
            </a>
        </div>
    </x-card>

    <p class="text-center small text-muted mt-3 mb-0">
        You can also
        <a href="{{ route('password.request') }}">request a reset link by email</a>
        if you no longer know your current password.
    </p>
@endsection