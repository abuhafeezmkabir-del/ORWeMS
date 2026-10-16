@extends('layouts.app')

@section('title', 'Health Records — ORWeMS')

@section('main')
    <x-page-header
        title="Health Records"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — {{ $beneficiary->first_name }} {{ $beneficiary->last_name }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Health' => null]"
    >
        @can('create', [\App\Models\HealthRecord::class, $beneficiary])
            <x-button variant="outline-success" size="sm" icon="bi-heart-pulse" :href="route('beneficiaries.health.create', $beneficiary)">
                Record health record
            </x-button>
        @endcan
    </x-page-header>

    @if ($openRecords->isEmpty())
        <x-alert type="info" class="mb-4">
            <div>
                No open health records for this child
                @can('create', [\App\Models\HealthRecord::class, $beneficiary])
                    — <a href="{{ route('beneficiaries.health.create', $beneficiary) }}" class="alert-link">record one</a>.
                @endcan
            </div>
        </x-alert>
    @else
        <x-alert type="warning" class="mb-4">
            <div>
                <strong>{{ $openRecords->count() }} open {{ \Illuminate\Support\Str::plural('record', $openRecords->count()) }}.</strong>
                Each is tracked until it is closed.
            </div>
        </x-alert>
    @endif

    <x-card title="All health records" subtitle="Newest first. Closed rows remain — health history is never deleted.">
        @if ($records->isEmpty())
            <p class="small text-muted mb-0">No health records have been recorded for this child.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Concern</th>
                            <th>Summary</th>
                            <th>Recorded</th>
                            <th>Follow-up</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $record->healthConcern?->name ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($record->summary, 80) }}</td>
                                <td>{{ $record->record_date?->format('d M Y') }}</td>
                                <td>
                                    @if ($record->follow_up_status === 'pending')
                                        <x-badge variant="warning">Pending</x-badge>
                                    @elseif ($record->follow_up_status === 'done')
                                        <x-badge variant="success">Done</x-badge>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :variant="$record->isOpen() ? 'warning' : 'secondary'">
                                        {{ $record->isOpen() ? 'Open' : 'Closed' }}
                                    </x-badge>
                                </td>
                                <td class="text-end">
                                    @can('view', $record)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('beneficiaries.health.show', [$beneficiary, $record]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @if ($record->isOpen())
                                        @can('update', $record)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('beneficiaries.health.edit', [$beneficiary, $record]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('close', $record)
                                            <a class="btn btn-sm btn-outline-warning" href="{{ route('beneficiaries.health.close', [$beneficiary, $record]) }}">
                                                <i class="bi bi-door-closed"></i>
                                            </a>
                                        @endcan
                                    @else
                                        <span class="text-muted fst-italic">
                                            {{ $record->closure_reason ?? 'closed' }}
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
