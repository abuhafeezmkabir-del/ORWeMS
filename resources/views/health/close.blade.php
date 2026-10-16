@extends('layouts.app')

@section('title', 'Close Health Record — ORWeMS')

@section('main')
    <x-page-header
        title="Close Health Record"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Close health record' => null]"
    />

    <x-alert type="warning" class="mb-4">
        <div>
            <strong>This ends tracking — it does not erase it.</strong>
            The record stays in {{ $beneficiary->first_name }}’s history with its
            end date. A closed record cannot be amended or re-closed. This action
            is itself audited.
        </div>
    </x-alert>

    <x-card title="Confirm closure" class="mb-4">
        <dl class="row mb-3 small">
            <dt class="col-sm-4 text-muted">Concern</dt>
            <dd class="col-sm-8 fw-semibold text-dark">{{ $record->healthConcern?->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Summary</dt>
            <dd class="col-sm-8">{{ $record->summary }}</dd>
            <dt class="col-sm-4 text-muted">Recorded</dt>
            <dd class="col-sm-8">{{ $record->record_date?->format('d M Y') }}</dd>
        </dl>

        <form method="POST" action="{{ route('beneficiaries.health.close.confirm', [$beneficiary, $record]) }}" novalidate>
            @csrf
            <div class="row">
                <div class="col-12 col-md-6">
                    <x-form-input name="end_date" label="End date" type="date"
                                  :value="now()->toDateString()" required />
                </div>
                <div class="col-12 col-md-6">
                    <x-form-input name="closure_reason" label="Closure reason" maxlength="255"
                                  placeholder="e.g. referral completed, healed" />
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-door-closed me-1"></i>Close this record
                </button>
                <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                    Keep record open
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
