@extends('layouts.app')

@section('title', 'Record Placement — ORWeMS')

@section('main')
    <x-page-header
        title="Record Care Placement"
        subtitle="Where is {{ $beneficiary->first_name }} living, and with whom?"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Record placement' => null]"
    />

    @if ($beneficiary->placements()->whereNull('end_date')->exists())
        <x-alert type="warning" class="mb-4">
            <div>
                <strong>This child already has an active placement.</strong>
                Record a change instead — it closes the current arrangement and opens the next one together.
            </div>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('beneficiaries.placements.store', $beneficiary) }}" novalidate>
        @csrf

        @include('placements.partials.form', ['placement' => null])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-house-add">
                Record Placement
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection