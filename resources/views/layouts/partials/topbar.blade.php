<header class="app-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebarToggleBtn" aria-label="Toggle navigation menu">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="d-none d-md-block">
            <span class="fw-bold text-dark">ORWeMS</span>
            <span class="text-muted mx-2">|</span>
            <span class="text-secondary small">Welfare Initiative for Children, Orphans and Vulnerable</span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        {{-- Notification placeholder --}}
        <button type="button" class="btn btn-light position-relative p-2" title="Notifications (Planned module)" disabled aria-label="Notifications">
            <i class="bi bi-bell text-secondary fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-secondary border border-light rounded-circle" style="width: 8px; height: 8px;">
                <span class="visually-hidden">No unread notifications</span>
            </span>
        </button>

        {{-- Authenticated account menu. Identity is taken from the signed-in
             user; ORWeMS has no roles yet, so no role label is shown. --}}
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 py-1 px-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 600;">
                    <i class="bi bi-person"></i>
                </span>
                <span class="d-none d-sm-inline-block text-start small">
                    <span class="d-block fw-semibold text-truncate" style="max-width: 140px;">{{ auth()->user()?->name ?? 'Guest' }}</span>
                    <span class="d-block text-muted" style="font-size: 0.725rem;">{{ auth()->user() ? ucfirst(auth()->user()->status->value) . ' account' : 'Not signed in' }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <h6 class="dropdown-header">
                        <div class="text-truncate" style="max-width: 200px;">{{ auth()->user()?->email }}</div>
                    </h6>
                </li>
                <li><a class="dropdown-item disabled" href="#">Profile Settings <span class="badge bg-secondary ms-2 small">Planned</span></a></li>
                {{-- Phase 3D — the authenticated change-password screen. Kept
                     as a real link because this is where an operator expects
                     to find it, and because it is the screen that resolves a
                     password-expiry redirect. --}}
                <li><a class="dropdown-item" href="{{ route('password.change') }}"><i class="bi bi-shield-lock me-2"></i>Change password</a></li>
                <li><a class="dropdown-item disabled" href="#">Security &amp; MFA <span class="badge bg-secondary ms-2 small">Planned</span></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    {{-- POST + CSRF: signing out must not be triggerable by a
                         link, a prefetch, or a third-party page. --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign out
                        </button>
                    </form>
                </li>
                <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-box-arrow-up-right me-2"></i>Public portal</a></li>
            </ul>
        </div>
    </div>
</header>
