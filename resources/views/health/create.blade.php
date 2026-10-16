@extends('layouts.app')

@section('title', 'Record Health Record — ORWeMS')

@section('main')
    <x-page-header
        title="Record Health Record"
        subtitle="What health matter needs tracking for {{ $beneficiary->first_name }}?"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Record health record' => null]"
    />

    <x-alert type="info" class="mb-4">
        <div>
            <strong>Casework-level only.</strong>
            Record what is being tracked and who needs to act — never diagnoses,
            prescriptions, lab results or other clinical detail.
            Several different concerns may be open at once; the same concern
            twice open is refused.
        </div>
    </x-alert>

    <form method="POST" action="{{ route('beneficiaries.health.store', $beneficiary) }}" novalidate>
        @csrf

        @include('health.partials.form', ['record' => null])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-heart-pulse">
                Record Health Record
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
