@extends('layouts.app')

@section('title', 'Beneficiary Case File — ORWeMS')

@section('main')
    <x-page-header
        title="{{ $beneficiary->first_name }} {{ $beneficiary->last_name }}"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => null]"
    >
        @can('update', $beneficiary)
            <x-button variant="outline-secondary" size="sm" icon="bi-pencil" :href="route('beneficiaries.edit', $beneficiary)">
                Amend
            </x-button>
        @endcan
        @can('archive', $beneficiary)
            <x-button variant="outline-danger" size="sm" icon="bi-archive" :href="route('beneficiaries.archive', $beneficiary)">
                Archive
            </x-button>
        @endcan
    </x-page-header>

    @if ($beneficiary->status === \App\Enums\BeneficiaryStatus::Active)
        <x-alert type="success" class="mb-4">
            <div><strong>Verified.</strong> This registration has been confirmed against evidence.</div>
        </x-alert>
    @elseif ($beneficiary->status === \App\Enums\BeneficiaryStatus::Registered)
        <x-alert type="warning" class="mb-4">
            <div>
                <strong>Awaiting verification.</strong> This record was registered but not yet confirmed.
                @can('verify', $beneficiary)
                    <form method="POST" action="{{ route('beneficiaries.verify', $beneficiary) }}" class="d-inline ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-patch-check me-1"></i>Verify now
                        </button>
                    </form>
                @endcan
            </div>
        </x-alert>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <x-card title="Identity" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Reference</dt>
                    <dd class="col-sm-8"><code>{{ $beneficiary->beneficiary_ref }}</code></dd>
                    <dt class="col-sm-4 text-muted">Full name</dt>
                    <dd class="col-sm-8 fw-semibold text-dark">{{ trim($beneficiary->first_name.' '.($beneficiary->middle_name ? $beneficiary->middle_name.' ' : '').$beneficiary->last_name) }}</dd>
                    <dt class="col-sm-4 text-muted">Sex</dt>
                    <dd class="col-sm-8">{{ ucfirst($beneficiary->sex->value) }}</dd>
                    <dt class="col-sm-4 text-muted">Date of birth</dt>
                    <dd class="col-sm-8">{{ $beneficiary->date_of_birth?->format('d M Y') ?? 'Not recorded' }} <span class="text-muted">({{ ucfirst(($beneficiary->date_of_birth_accuracy?->value) ?? 'unknown') }})</span></dd>
                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8">{{ ucfirst($beneficiary->status->value) }}</dd>
                    <dt class="col-sm-4 text-muted">Registered</dt>
                    <dd class="col-sm-8">{{ $beneficiary->registration_date?->format('d M Y') }}</dd>
                </dl>
            </x-card>

            <x-card title="Location" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">State</dt>
                    <dd class="col-sm-8">{{ $beneficiary->state->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">LGA</dt>
                    <dd class="col-sm-8">{{ $beneficiary->localGovernmentArea->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Ward</dt>
                    <dd class="col-sm-8">{{ $beneficiary->ward->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Community</dt>
                    <dd class="col-sm-8">{{ $beneficiary->community->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Household</dt>
                    <dd class="col-sm-8">{{ $beneficiary->household ? ($beneficiary->household->household_code.' — '.$beneficiary->household->household_name) : '—' }}</dd>
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-5">
            <x-card title="Programme" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Programme</dt>
                    <dd class="col-sm-8">{{ $beneficiary->programme->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Code</dt>
                    <dd class="col-sm-8"><code>{{ $beneficiary->programme->code ?? '—' }}</code></dd>
                </dl>
            </x-card>

            @if ($beneficiary->notes)
                <x-card title="Case notes" class="mb-4">
                    <p class="small mb-0">{{ $beneficiary->notes }}</p>
                </x-card>
            @endif

            <x-card title="Record history" subtitle="Verification and lifecycle, not case content">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Registered</span>
                        <span>{{ $beneficiary->created_at?->format('d M Y, H:i') }}</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Last amended</span>
                        <span>{{ $beneficiary->updated_at?->format('d M Y, H:i') }}</span>
                    </li>
                </ul>
            </x-card>
        </div>
    </div>
@endsection
