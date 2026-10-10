@extends('layouts.app')

@section('title', 'Placement History — ORWeMS')

@section('main')
    <x-page-header
        title="Placement History"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — {{ $beneficiary->first_name }} {{ $beneficiary->last_name }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Placements' => null]"
    >
        @can('create', [\App\Models\Placement::class, $beneficiary])
            <x-button variant="outline-success" size="sm" icon="bi-house-add" :href="route('beneficiaries.placements.create', $beneficiary)">
                Record placement
            </x-button>
        @endcan
    </x-page-header>

    @if ($currentPlacement === null)
        <x-alert type="info" class="mb-4">
            <div>
                No active placement is recorded for this child
                @can('create', [\App\Models\Placement::class, $beneficiary])
                    — <a href="{{ route('beneficiaries.placements.create', $beneficiary) }}" class="alert-link">record one</a>.
                @endcan
            </div>
        </x-alert>
    @else
        <x-alert type="success" class="mb-4">
            <div>
                <strong>Current arrangement:</strong>
                {{ $currentPlacement->careArrangementType?->name ?? '—' }}
                since {{ $currentPlacement->start_date?->format('d M Y') }}.
                @can('close', $currentPlacement)
                    <a href="{{ route('beneficiaries.placements.change', $beneficiary) }}" class="alert-link">Change</a>
                    or
                    <a href="{{ route('beneficiaries.placements.close', [$beneficiary, $currentPlacement]) }}" class="alert-link">close</a>
                    it when that changes.
                @endcan
            </div>
        </x-alert>
    @endif

    <x-card title="All placements" subtitle="Newest first. Closed rows remain — care history is never deleted.">
        @if ($placements->isEmpty())
            <p class="small text-muted mb-0">No placements have been recorded for this child.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Arrangement</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Household</th>
                            <th>Caregiver</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($placements->reverse() as $placement)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $placement->careArrangementType?->name ?? '—' }}</td>
                                <td>{{ $placement->start_date?->format('d M Y') }}</td>
                                <td>{{ $placement->end_date?->format('d M Y') ?? 'present' }}</td>
                                <td>
                                    @if ($placement->household)
                                        <code>{{ $placement->household->household_code }}</code>
                                        {{ $placement->household->household_name }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    {{ trim(($placement->caregiverPerson?->first_name ?? '').' '.($placement->caregiverPerson?->last_name ?? '')) ?: '—' }}
                                </td>
                                <td>
                                    <x-badge :variant="$placement->isActive() ? 'success' : 'secondary'">
                                        {{ $placement->isActive() ? 'Current' : 'Closed' }}
                                    </x-badge>
                                </td>
                                <td class="text-end">
                                    @if ($placement->isActive())
                                        @can('update', $placement)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('beneficiaries.placements.edit', [$beneficiary, $placement]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('close', $placement)
                                            <a class="btn btn-sm btn-outline-warning" href="{{ route('beneficiaries.placements.close', [$beneficiary, $placement]) }}">
                                                <i class="bi bi-door-closed"></i>
                                            </a>
                                        @endcan
                                    @else
                                        <span class="text-muted fst-italic">
                                            {{ $placement->closure_reason ?? 'closed' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection