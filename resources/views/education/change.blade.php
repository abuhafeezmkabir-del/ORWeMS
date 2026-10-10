@extends('layouts.app')

@section('title', 'Change Enrolment — ORWeMS')

@section('main')
    <x-page-header
        title="Change Education Enrolment"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — close the current enrolment and open the next, together"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Change enrolment' => null]"
    />

    <x-alert type="info" class="mb-4">
        <div>
            <strong>A child is enrolled in one place at a time.</strong>
            This screen records both halves of the move in one step: the current
            enrolment is closed with an end date, and the next one opens — or
            nothing is written at all.
        </div>
    </x-alert>

    <form method="POST" action="{{ route('beneficiaries.education.change.store', $beneficiary) }}" novalidate>
        @csrf

        <x-card title="Closing the current enrolment" class="mb-4">
            <dl class="row mb-3 small">
                <dt class="col-sm-4 text-muted">Current school</dt>
                <dd class="col-sm-8 fw-semibold text-dark">
                    {{ $currentEnrolment->institution_name }}
                    ({{ $currentEnrolment->educationLevel?->name ?? '—' }},
                    since {{ $currentEnrolment->start_date?->format('d M Y') }})
                </dd>
            </dl>
            <div class="row">
                <div class="col-12 col-md-6">
                    <x-form-input name="current_end_date" label="End date of the current enrolment" type="date"
                                  :value="now()->toDateString()" required />
                </div>
                <div class="col-12 col-md-6">
                    <x-form-input name="current_closure_reason" label="Closure reason" maxlength="255"
                                  placeholder="e.g. moved to a new school" />
                </div>
            </div>
        </x-card>

        <x-card title="Opening the next enrolment" class="mb-4">
            @include('education.partials.form', ['enrolment' => null])
        </x-card>

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-arrow-repeat">
                Change Enrolment
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
