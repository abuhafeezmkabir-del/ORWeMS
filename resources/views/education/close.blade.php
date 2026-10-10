@extends('layouts.app')

@section('title', 'Close Enrolment — ORWeMS')

@section('main')
    <x-page-header
        title="Close Education Enrolment"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Close enrolment' => null]"
    />

    <x-alert type="warning" class="mb-4">
        <div>
            <strong>This ends the enrolment — it does not erase it.</strong>
            The enrolment stays in {{ $beneficiary->first_name }}’s history with its
            end date. A closed enrolment cannot be amended or re-closed, and a new
            enrolment can then be recorded. This action is itself audited.
        </div>
    </x-alert>

    <x-card title="Confirm closure" class="mb-4">
        <dl class="row mb-3 small">
            <dt class="col-sm-4 text-muted">School</dt>
            <dd class="col-sm-8 fw-semibold text-dark">{{ $enrolment->institution_name }}</dd>
            <dt class="col-sm-4 text-muted">Level</dt>
            <dd class="col-sm-8">{{ $enrolment->educationLevel?->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Started</dt>
            <dd class="col-sm-8">{{ $enrolment->start_date?->format('d M Y') }}</dd>
        </dl>

        <form method="POST" action="{{ route('beneficiaries.education.close.confirm', [$beneficiary, $enrolment]) }}" novalidate>
            @csrf
            <div class="row">
                <div class="col-12 col-md-6">
                    <x-form-input name="end_date" label="End date" type="date"
                                  :value="now()->toDateString()" required />
                </div>
                <div class="col-12 col-md-6">
                    <x-form-input name="closure_reason" label="Closure reason" maxlength="255"
                                  placeholder="e.g. completed the school year" />
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-door-closed me-1"></i>Close this enrolment
                </button>
                <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                    Keep enrolment current
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
