@extends('layouts.app')

@section('title', 'Health Record — ORWeMS')

@section('main')
    <x-page-header
        title="{{ $record->healthConcern?->name ?? 'Health record' }}"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — recorded {{ $record->record_date?->format('d M Y') }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Health' => route('beneficiaries.health.index', $beneficiary), 'Record' => null]"
    >
        @if ($record->isOpen())
            @can('update', $record)
                <x-button variant="outline-secondary" size="sm" icon="bi-pencil" :href="route('beneficiaries.health.edit', [$beneficiary, $record])">
                    Amend
                </x-button>
            @endcan
            @can('close', $record)
                <x-button variant="outline-warning" size="sm" icon="bi-door-closed" :href="route('beneficiaries.health.close', [$beneficiary, $record])">
                    Close
                </x-button>
            @endcan
        @endif
    </x-page-header>

    @if (! $record->isOpen())
        <x-alert type="secondary" class="mb-4">
            <div>
                <strong>Closed {{ $record->end_date?->format('d M Y') }}.</strong>
                This record is history and cannot be amended.
                @if ($record->closure_reason !== null)
                    {{ $record->closure_reason }}
                @endif
            </div>
        </x-alert>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <x-card title="Record" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Concern</dt>
                    <dd class="col-sm-8 fw-semibold text-dark">{{ $record->healthConcern?->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Summary</dt>
                    <dd class="col-sm-8">{{ $record->summary }}</dd>
                    <dt class="col-sm-4 text-muted">Record date</dt>
                    <dd class="col-sm-8">{{ $record->record_date?->format('d M Y') }}</dd>
                    @if (! $record->isOpen())
                        <dt class="col-sm-4 text-muted">End date</dt>
                        <dd class="col-sm-8">{{ $record->end_date?->format('d M Y') }}</dd>
                    @endif
                    @if ($record->notes !== null)
                        <dt class="col-sm-4 text-muted">Notes</dt>
                        <dd class="col-sm-8">{{ $record->notes }}</dd>
                    @endif
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-5">
            <x-card title="Follow-up" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8">
                        @if ($record->follow_up_status === 'pending')
                            <x-badge variant="warning">Follow-up pending</x-badge>
                        @elseif ($record->follow_up_status === 'done')
                            <x-badge variant="success">Follow-up done</x-badge>
                        @else
                            <span class="text-muted">No follow-up needed</span>
                        @endif
                    </dd>
                    @if ($record->referral_to !== null)
                        <dt class="col-sm-4 text-muted">Referred to</dt>
                        <dd class="col-sm-8">{{ $record->referral_to }}</dd>
                    @endif
                    <dt class="col-sm-4 text-muted">State</dt>
                    <dd class="col-sm-8">
                        <x-badge :variant="$record->isOpen() ? 'warning' : 'secondary'">
                            {{ $record->isOpen() ? 'Open' : 'Closed' }}
                        </x-badge>
                    </dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
