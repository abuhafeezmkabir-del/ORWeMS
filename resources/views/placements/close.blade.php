@extends('layouts.app')

@section('title', 'Close Placement — ORWeMS')

@section('main')
    <x-page-header
        title="Close Care Placement"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Close placement' => null]"
    />

    <x-alert type="warning" class="mb-4">
        <div>
            <strong>This ends the arrangement — it does not erase it.</strong>
            The placement stays in {{ $beneficiary->first_name }}’s history with its
            end date. A closed placement cannot be amended or re-closed, and a new
            placement can then be recorded. This action is itself audited.
        </div>
    </x-alert>

    <x-card title="Confirm closure" class="mb-4">
        <dl class="row mb-3 small">
            <dt class="col-sm-4 text-muted">Arrangement</dt>
            <dd class="col-sm-8 fw-semibold text-dark">{{ $placement->careArrangementType?->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Started</dt>
            <dd class="col-sm-8">{{ $placement->start_date?->format('d M Y') }}</dd>
            <dt class="col-sm-4 text-muted">Household</dt>
            <dd class="col-sm-8">
                @if ($placement->household)
                    <code>{{ $placement->household->household_code }}</code> {{ $placement->household->household_name }}
                @else
                    —
                @endif
            </dd>
        </dl>

        <form method="POST" action="{{ route('beneficiaries.placements.close.confirm', [$beneficiary, $placement]) }}" novalidate>
            @csrf
            <div class="row">
                <div class="col-12 col-md-6">
                    <x-form-input name="end_date" label="End date" type="date"
                                  :value="now()->toDateString()" required />
                </div>
                <div class="col-12 col-md-6">
                    <x-form-input name="closure_reason" label="Closure reason" maxlength="255"
                                  placeholder="e.g. returned to family" />
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-door-closed me-1"></i>Close this placement
                </button>
                <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                    Keep placement active
                </x-button>
            </div>
        </form>
    </x-card>
@endsection