<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ORWeMS Demo') — Demonstration Mode</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">

    <style>
        body { background: #f8f9fa; }
        .demo-banner {
            background: #ffc107;
            color: #000;
            padding: 0.5rem 1rem;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #d39e00;
            position: sticky;
            top: 0;
            z-index: 1050;
        }
        .demo-banner i { margin-right: 0.5rem; }
        .nav-demo { background: #fff; border-bottom: 1px solid #dee2e6; }
        .nav-demo .nav-link { color: #495057; padding: 0.75rem 1rem; }
        .nav-demo .nav-link:hover { color: #0d6efd; background: #f8f9fa; }
        .nav-demo .nav-link.active { color: #0d6efd; font-weight: 500; border-bottom: 2px solid #0d6efd; }
        .card-demo { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .simulated-badge { background: #e9ecef; color: #6c757d; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body class="bg-light">

<div class="demo-banner">
    <i class="bi bi-info-circle-fill"></i>
    <strong>DEMONSTRATION MODE</strong> — All records are fictional. Actions are simulated; no records are saved.
</div>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary nav-demo shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('demo.dashboard') }}">
            <i class="bi bi-shield-heart me-2"></i>
            <span>ORWeMS <span class="badge bg-warning text-dark">DEMO</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#demoNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="demoNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.dashboard') ? 'active' : '' }}" href="{{ route('demo.dashboard') }}"><i class="bi bi-speedometer me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.beneficiary') ? 'active' : '' }}" href="{{ route('demo.beneficiary', 'BEN-2026-001') }}"><i class="bi bi-person me-1"></i>Beneficiary Profile</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.care.index') ? 'active' : '' }}" href="{{ route('demo.care.index', 'BEN-2026-001') }}"><i class="bi bi-house-heart me-1"></i>Care / Placement</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.education.index') ? 'active' : '' }}" href="{{ route('demo.education.index', 'BEN-2026-001') }}"><i class="bi bi-book me-1"></i>Education</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.health.index') ? 'active' : '' }}" href="{{ route('demo.health.index', 'BEN-2026-001') }}"><i class="bi bi-heart-pulse me-1"></i>Health Records</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('demo.relationships.index') ? 'active' : '' }}" href="{{ route('demo.relationships.index', 'BEN-2026-001') }}"><i class="bi bi-people me-1"></i>Relationships</a></li>
            </ul>
            <div class="d-flex">
                <form method="POST" action="{{ route('demo.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    @include('layouts.partials.flash-messages')

    @yield('content')
</div>

<footer class="bg-white border-top mt-5 py-4">
    <div class="container text-center text-muted small">
        ORWeMS Demonstration &bull; WICHOV &bull; PhD Research Platform &bull; {{ now()->year }}
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')

</body>
</html>