@extends('layouts.app')

@section('title', 'Amend Health Record — ORWeMS')

@section('main')
    <x-page-header
        title="Amend Health Record"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — {{ $record->healthConcern?->name ?? 'Health record' }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Health' => route('beneficiaries.health.index', $beneficiary), 'Amend' => null]"
    />

    <form method="POST" action="{{ route('beneficiaries.health.update', [$beneficiary, $record]) }}" novalidate>
        @csrf
        @method('PUT')

        @include('health.partials.form', ['record' => $record])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-pencil">
                Save Changes
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.health.show', [$beneficiary, $record])">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
