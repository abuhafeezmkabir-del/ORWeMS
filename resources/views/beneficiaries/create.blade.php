@extends('layouts.app')

@section('title', 'Register Beneficiary — ORWeMS')

@section('main')
    <x-page-header
        title="Register Beneficiary"
        subtitle="A new registration starts as Registered — verification is a separate step"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), 'Register' => null]"
    />

    <form method="POST" action="{{ route('beneficiaries.store') }}" novalidate>
        @csrf

        @include('beneficiaries.partials.form', ['beneficiary' => null])

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-person-plus">
                Register Beneficiary
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.index')">
                Cancel
            </x-button>
        </div>
    </form>
@endsection
