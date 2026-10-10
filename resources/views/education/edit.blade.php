@extends('layouts.app')

@section('title', 'Amend Enrolment — ORWeMS')

@section('main')
    <x-page-header
        title="Amend Education Enrolment"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — current enrolment since {{ $enrolment->start_date?->format('d M Y') }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Amend enrolment' => null]"
    />

    <x-alert type="info" class="mb-4">
        <div>
            <strong>This edits the enrolment, not its timeline.</strong>
            A current enrolment can be corrected here; ending it is a separate,
            audited action (<a href="{{ route('beneficiaries.education.close', [$beneficiary, $enrolment]) }}" class="alert-link">close enrolment</a>).
            Closed enrolments are history and cannot be amended.
        </div>
    </x-alert>

    <form method="POST" action="{{ route('beneficiaries.education.update', [$beneficiary, $enrolment]) }}" novalidate>
        @csrf
        @method('PUT')

        @include('education.partials.form', ['enrolment' => $enrolment])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-check-lg">
                Save Changes
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
