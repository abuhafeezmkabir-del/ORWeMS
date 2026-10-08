@extends('layouts.app')

@section('title', 'Amend Beneficiary — ORWeMS')

@section('main')
    <x-page-header
        title="Amend Beneficiary"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — lifecycle moves happen only through Verify and Archive"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Amend' => null]"
    />

    <form method="POST" action="{{ route('beneficiaries.update', $beneficiary) }}" novalidate>
        @csrf
        @method('PUT')

        @include('beneficiaries.partials.form', ['beneficiary' => $beneficiary])

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
