@extends('layouts.app')

@section('title', 'End Relationship — ORWeMS')

@section('main')
    <x-page-header
        title="End Relationship"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'End relationship' => null]"
    />

    <x-alert type="warning" class="mb-4">
        <div>
            <strong>This marks the arrangement as stopped — it does not erase it.</strong>
            The relationship stays in the record with its end date: the link
            existed, and now it has stopped. Ending an already-ended relationship
            is refused, and this action is audited.
        </div>
    </x-alert>

    <x-card title="Confirm ending" class="mb-4">
        <dl class="row mb-3 small">
            <dt class="col-sm-4 text-muted">Relationship</dt>
            <dd class="col-sm-8 fw-semibold text-dark">{{ $relationship->relationshipType?->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Recorded since</dt>
            <dd class="col-sm-8">{{ $relationship->start_date?->format('d M Y') }}</dd>
        </dl>

        <form method="POST" action="{{ route('beneficiaries.relationships.end.confirm', [$beneficiary, $relationship]) }}" novalidate>
            @csrf
            <x-form-input name="end_date" label="End date" type="date"
                          :value="now()->toDateString()" required />
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-dash-circle me-1"></i>End this relationship
                </button>
                <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                    Keep relationship open
                </x-button>
            </div>
        </form>
    </x-card>
@endsection