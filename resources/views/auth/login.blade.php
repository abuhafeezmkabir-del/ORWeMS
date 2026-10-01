@extends('layouts.guest')

@section('title', 'Sign in')

@section('main')
    <div class="text-center mb-4">
        <i class="bi bi-shield-heart fs-1 text-primary"></i>
        <h1 class="h4 fw-bold mt-2 mb-1">ORWeMS</h1>
        <p class="text-muted small mb-0">
            Orphans Registration &amp; Welfare Management System
        </p>
    </div>

    <x-card>
        <h2 class="h6 fw-bold mb-3">Sign in to your account</h2>

        {{-- Status only. Field-level problems are rendered by the input itself,
             so a summary box is not repeated here. --}}
        @if (session('status'))
            <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" novalidate>
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

            <x-form-input
                name="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required
            />

            <x-button type="submit" class="w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Sign in
            </x-button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('password.request') }}" class="small">Forgot your password?</a>
        </div>
    </x-card>

    <p class="text-center small mt-3 mb-0">
        <a href="{{ route('home') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to the public portal
        </a>
    </p>

    <p class="text-center small text-muted mt-3 mb-0">
        Access is restricted to authorised WICHOV personnel.
    </p>
@endsection