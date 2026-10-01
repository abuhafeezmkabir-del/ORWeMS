@extends('layouts.app')

@section('title', 'System Dashboard — ORWeMS')

@section('main')
    <x-page-header
        title="Humanitarian Operations Dashboard"
        subtitle="Orphans Registration &amp; Welfare Management System (ORWeMS) — Operational Overview"
        :breadcrumbs="['Home' => url('/'), 'Operations' => null, 'Dashboard' => null]"
    >
        <x-button variant="outline-primary" size="sm" icon="bi-arrow-clockwise" href="{{ route('dashboard') }}">
            Refresh
        </x-button>
        <x-button variant="primary" size="sm" icon="bi-person-plus" :disabled="true" title="Beneficiary registration will be available in subsequent phase">
            Register Beneficiary
        </x-button>
    </x-page-header>

    {{-- System Status Notice --}}
    <x-alert type="info" class="mb-4">
        <div>
            <strong>Phase 1A Foundation Mode:</strong>
            The visual and structural shell of ORWeMS is active. Metric cards below are structural indicators representing future operational indicators. No beneficiary or case records are fabricated.
        </div>
    </x-alert>

    {{-- Structural Metric Indicators (Zero / Placeholder values) --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card
                label="Registered Beneficiaries"
                value="0"
                icon="bi-people"
                variant="primary"
                hint="Structural placeholder — awaiting Phase 2"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card
                label="Active Cases"
                value="0"
                icon="bi-folder2-open"
                variant="success"
                hint="Structural placeholder — awaiting Phase 2"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card
                label="Pending Assessments"
                value="0"
                icon="bi-clipboard-check"
                variant="warning"
                hint="Structural placeholder — awaiting Phase 2"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card
                label="High-Priority Cases"
                value="0"
                icon="bi-exclamation-octagon"
                variant="danger"
                hint="Structural placeholder — awaiting Phase 2"
            />
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- System Architecture & Readiness Card --}}
        <div class="col-12 col-lg-8">
            <x-card title="System Readiness &amp; Humanitarian Framework" subtitle="Architectural baseline for WICHOV pilot deployment">
                <p class="text-secondary small mb-3">
                    ORWeMS is designed to support structured vulnerability profiling, intervention tracking, and decision-support algorithms (including the Index of Vulnerability and Welfare Assessment — IWAE). Current architecture reflects verified Phase 0 &amp; 1A foundations:
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                <strong class="small">Pilot Operational Scope</strong>
                            </div>
                            <div class="small text-secondary">
                                Pilot readiness targeted for <strong>Niger State</strong>, <strong>Nasarawa State</strong>, and the <strong>FCT</strong>, powered by an open geographic reference schema.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-shield-check text-success"></i>
                                <strong class="small">Data Governance &amp; Security</strong>
                            </div>
                            <div class="small text-secondary">
                                Sensitive child and guardian data requires strict role-based access control, cryptographic key protection, and immutable audit logging.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 border rounded">
                    <h6 class="fw-bold small mb-2 text-dark">Recent Case Activity Queue</h6>
                    <x-table :striped="true" :hover="true" :small="true">
                        <thead class="table-light small">
                            <tr>
                                <th>Case Reference</th>
                                <th>Category</th>
                                <th>Location (LGA)</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4 small">
                                    <i class="bi bi-inbox fs-4 d-block text-secondary mb-1"></i>
                                    No operational cases registered yet. Beneficiary intake opens in subsequent phase.
                                </td>
                            </tr>
                        </tbody>
                    </x-table>
                </div>
            </x-card>
        </div>

        {{-- Planned Capabilities & Quick Actions --}}
        <div class="col-12 col-lg-4">
            <x-card title="Humanitarian Modules" subtitle="Lifecycle coverage roadmap" class="h-100">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-person-badge text-primary me-2"></i>Beneficiary Intake</span>
                        <x-badge variant="secondary">Planned</x-badge>
                    </li>
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-clipboard2-pulse text-warning me-2"></i>Welfare Assessments</span>
                        <x-badge variant="secondary">Planned</x-badge>
                    </li>
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-heart-half text-danger me-2"></i>Targeted Interventions</span>
                        <x-badge variant="secondary">Planned</x-badge>
                    </li>
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-mortarboard text-info me-2"></i>Education Support</span>
                        <x-badge variant="secondary">Planned</x-badge>
                    </li>
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-hospital text-success me-2"></i>Healthcare &amp; Nutrition</span>
                        <x-badge variant="secondary">Planned</x-badge>
                    </li>
                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-cpu text-dark me-2"></i>IWAE Decision Engine</span>
                        <x-badge variant="secondary">Research</x-badge>
                    </li>
                </ul>

                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold small mb-2">Technical Baseline</h6>
                    <div class="small text-muted mb-1">Framework: <strong>Laravel 12 (Blade + Bootstrap 5)</strong></div>
                    <div class="small text-muted mb-1">Database: <strong>MySQL 8.4 (Port 3308)</strong></div>
                    <div class="small text-muted">Hosting Target: <strong>cPanel / Shared-Hosting Compatible</strong></div>
                </div>
            </x-card>
        </div>
    </div>
@endsection