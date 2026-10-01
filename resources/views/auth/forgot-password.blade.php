@extends('layouts.guest')

@section('title', 'Forgot password')

@section('main')
    <div class="text-center mb-4">
        <i class="bi bi-shield-heart fs-1 text-primary"></i>
        <h1 class="h5 fw-bold mt-2 mb-1">Reset your password</h1>
        <p class="text-muted small mb-0">
            Enter the email address on your ORWeMS account and we will send you a reset link.
        </p>
    </div>

    <x-card>
        @if (session('status'))
            <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <x-form-input
                name="email"
                label="Email address"
                type="email"
                placeholder="you@wichov.org"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                required
                autofocus
            />

            <x-button type="submit" class="w-100">
                <i class="bi bi-envelope me-1"></i>
                Email password reset link
            </x-button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small">
                <i class="bi bi-arrow-left me-1"></i>Back to sign in
            </a>
        </div>
    </x-card>
@endsection