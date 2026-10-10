@extends('layouts.app')

@section('title', 'Amend Placement — ORWeMS')

@section('main')
    <x-page-header
        title="Amend Care Placement"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — active placement since {{ $placement->start_date?->format('d M Y') }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Amend placement' => null]"
    />

    <x-alert type="info" class="mb-4">
        <div>
            <strong>This edits the arrangement, not its timeline.</strong>
            An active placement can be corrected here; ending it is a separate,
            audited action (<a href="{{ route('beneficiaries.placements.close', [$beneficiary, $placement]) }}" class="alert-link">close placement</a>).
            Closed placements are history and cannot be amended.
        </div>
    </x-alert>

    <form method="POST" action="{{ route('beneficiaries.placements.update', [$beneficiary, $placement]) }}" novalidate>
        @csrf
        @method('PUT')

        @include('placements.partials.form', ['placement' => $placement])

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