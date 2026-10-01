@extends('layouts.base')

@section('body-class', 'bg-light')

@section('content')
    {{-- Shell for unauthenticated pages (sign in, password reset). No sidebar,
         no topbar: an unauthenticated visitor has no account context to show. --}}
    <div class="d-flex flex-column min-vh-100">

        <main class="flex-grow-1 d-flex align-items-center justify-content-center py-5 px-3">
            <div class="w-100" style="max-width: 420px;">
                @yield('main')
            </div>
        </main>

        <footer class="text-center text-muted small pb-4 px-3">
            <div class="mb-1">
                <strong class="text-dark">ORWeMS</strong> &copy; {{ date('Y') }} —
                <span>WICHOV (Welfare Initiative for Children, Orphans and Vulnerable)</span>
            </div>
            <div>
                <span>Designed &amp; Developed by</span>
                <a href="https://qafamstech.com.ng/" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-semibold">
                    Qafam Software Technology (Qafam Soft-Tech)
                </a>
            </div>
        </footer>
    </div>
@endsection