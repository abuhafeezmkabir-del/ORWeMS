<aside class="app-sidebar" id="appSidebar" aria-label="Sidebar Navigation">
    <div class="sidebar-brand">
        <a href="{{ url('/') }}" class="text-white text-decoration-none d-flex align-items-center">
            <i class="bi bi-shield-heart fs-4 me-2 text-primary"></i>
            <span>ORWeMS</span>
        </a>
        <span class="sidebar-brand-badge">WICHOV</span>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-heading">Core</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-heading">Beneficiaries</div>
        {{-- Shown only to operators who may read them; the routes enforce
             permission:beneficiaries.* independently, so knowing the URL
             without the permission yields a 403, not a different page. --}}
        @can('beneficiaries.view')
            <a href="{{ route('beneficiaries.index') }}" class="sidebar-link {{ request()->routeIs('beneficiaries.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>All Beneficiaries</span>
            </a>
        @else
            <a href="#" class="sidebar-link disabled" title="No access" tabindex="-1" aria-disabled="true">
                <i class="bi bi-people"></i>
                <span>All Beneficiaries</span>
            </a>
        @endcan
        @can('beneficiaries.create')
            <a href="{{ route('beneficiaries.create') }}" class="sidebar-link {{ request()->routeIs('beneficiaries.create') ? 'active' : '' }}">
                <i class="bi bi-person-plus"></i>
                <span>Register Beneficiary</span>
            </a>
        @else
            <a href="#" class="sidebar-link disabled" title="No access" tabindex="-1" aria-disabled="true">
                <i class="bi bi-person-plus"></i>
                <span>Register Beneficiary</span>
            </a>
        @endcan

        <div class="sidebar-heading">Welfare Management</div>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-clipboard2-check"></i>
            <span>Assessments</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-heart-pulse"></i>
            <span>Interventions</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-arrow-repeat"></i>
            <span>Follow-up</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>

        <div class="sidebar-heading">Care &amp; Family</div>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-person-vcard"></i>
            <span>Guardians &amp; Caregivers</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-house-heart"></i>
            <span>Placements</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>

        <div class="sidebar-heading">Sectoral Modules</div>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-mortarboard"></i>
            <span>Education</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-hospital"></i>
            <span>Health</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>

        <div class="sidebar-heading">Intelligence</div>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-graph-up"></i>
            <span>Reports &amp; Analytics</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-journal-text"></i>
            <span>Research Portal</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>

        <div class="sidebar-heading">Administration</div>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-person-gear"></i>
            <span>Users</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>

        {{-- Roles & Permissions is only shown to operators who may read them.
             Hiding the link is a usability measure only: the route enforces
             permission:roles.view independently. An operator who knows the URL
             will receive a 403, not a different page. --}}
        @can('roles.view')
            <a href="{{ route('administration.roles.index') }}"
               class="sidebar-link {{ request()->routeIs('administration.roles.*', 'administration.permissions.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock"></i>
                <span>Roles &amp; Permissions</span>
            </a>
        @else
            <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
                <i class="bi bi-shield-lock"></i>
                <span>Roles &amp; Permissions</span>
                <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
            </a>
        @endcan

        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-geo-alt"></i>
            <span>Locations</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-building"></i>
            <span>Organisations</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
        <a href="#" class="sidebar-link disabled" title="Planned module" tabindex="-1" aria-disabled="true">
            <i class="bi bi-clock-history"></i>
            <span>Audit Log</span>
            <span class="badge bg-secondary ms-auto text-uppercase" style="font-size: 0.65rem;">Planned</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="fw-semibold text-truncate">WICHOV Platform</div>
        <div class="text-secondary small">Phase 3B Authorization</div>
    </div>
</aside>
