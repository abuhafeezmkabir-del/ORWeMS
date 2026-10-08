@extends('layouts.app')

@section('title', 'Archive Beneficiary — ORWeMS')

@section('main')
    <x-page-header
        title="Archive Beneficiary"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Archive' => null]"
    />

    <x-alert type="warning" class="mb-4">
        <div>
            <strong>This removes the record from active view — it does not destroy it.</strong>
            The beneficiary leaves the register but is retained for history and audit.
            This action is itself audited.
        </div>
    </x-alert>

    <x-card title="Confirm archiving" class="mb-4">
        <p class="small text-muted">
            You are about to archive
            <strong class="text-dark">{{ $beneficiary->first_name }} {{ $beneficiary->last_name }}</strong>
            (<code>{{ $beneficiary->beneficiary_ref }}</code>).
        </p>
        <form method="POST" action="{{ route('beneficiaries.archive.confirm', $beneficiary) }}">
            @csrf
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-archive me-1"></i>Archive this record
                </button>
                <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                    Keep record active
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
