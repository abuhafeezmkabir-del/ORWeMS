@extends('layouts.app')

@section('title', 'Record Enrolment — ORWeMS')

@section('main')
    <x-page-header
        title="Record Education Enrolment"
        subtitle="Where is {{ $beneficiary->first_name }} studying?"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Record enrolment' => null]"
    />

    @if ($beneficiary->enrolments()->whereNull('end_date')->exists())
        <x-alert type="warning" class="mb-4">
            <div>
                <strong>This child already has a current enrolment.</strong>
                Record a change instead — it closes the current enrolment and opens the next one together.
            </div>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('beneficiaries.education.store', $beneficiary) }}" novalidate>
        @csrf

        @include('education.partials.form', ['enrolment' => null])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-mortarboard">
                Record Enrolment
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
