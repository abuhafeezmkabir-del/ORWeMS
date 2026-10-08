@extends('layouts.app')

@section('title', 'Beneficiaries — ORWeMS')

@section('main')
    <x-page-header
        title="Beneficiaries"
        subtitle="Registered orphans and vulnerable children under WICHOV care"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => null]"
    >
        @can('beneficiaries.create')
            <x-button variant="primary" size="sm" icon="bi-person-plus" :href="route('beneficiaries.create')">
                Register Beneficiary
            </x-button>
        @endcan
    </x-page-header>

    <x-alert type="info" class="mb-4">
        <div>
            Showing only beneficiaries within <strong>your programme and geographic scope</strong>.
            Records outside your scope are not listed and cannot be opened directly.
        </div>
    </x-alert>

    <x-card title="Search &amp; Filter" subtitle="Narrow the listing without leaving your scope" class="mb-4">
        <form method="GET" action="{{ route('beneficiaries.index') }}" class="row g-3">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label fw-semibold small">Reference or name</label>
                <input type="text" name="q" id="q" class="form-control"
                       value="{{ $filters['q'] ?? '' }}" placeholder="WICHOV-000001 or surname…">
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label fw-semibold small">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\BeneficiaryStatus::cases() as $status)
                        <option value="{{ $status->value }}"
                            @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label fw-semibold small">Registered from</label>
                <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label fw-semibold small">Registered to</label>
                <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Search
                </button>
                <a href="{{ route('beneficiaries.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </x-card>

    <x-card title="Beneficiary Register" subtitle="Records in scope" bodyClass="p-0">
        <x-table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Name</th>
                    <th>Sex</th>
                    <th>Programme</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($beneficiaries as $beneficiary)
                    <tr>
                        <td><code class="small">{{ $beneficiary->beneficiary_ref }}</code></td>
                        <td class="fw-semibold text-dark">{{ trim($beneficiary->first_name.' '.($beneficiary->middle_name ? $beneficiary->middle_name.' ' : '').$beneficiary->last_name) }}</td>
                        <td class="small">{{ ucfirst($beneficiary->sex->value) }}</td>
                        <td class="small">{{ $beneficiary->programme->name ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $beneficiary->state->name ?? '—' }}@if ($beneficiary->localGovernmentArea), {{ $beneficiary->localGovernmentArea->name }}@endif
                        </td>
                        <td>
                            @if ($beneficiary->status === \App\Enums\BeneficiaryStatus::Active)
                                <x-badge variant="success">Active</x-badge>
                            @elseif ($beneficiary->status === \App\Enums\BeneficiaryStatus::Registered)
                                <x-badge variant="warning">Registered</x-badge>
                            @else
                                <x-badge variant="secondary">{{ ucfirst($beneficiary->status->value) }}</x-badge>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('beneficiaries.show', $beneficiary) }}"
                               class="btn btn-sm btn-outline-primary" title="Open case file">
                                <i class="bi bi-eye"></i><span class="visually-hidden">View</span>
                            </a>
                            @can('update', $beneficiary)
                                <a href="{{ route('beneficiaries.edit', $beneficiary) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Amend record">
                                    <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4 small">
                            <i class="bi bi-inbox fs-4 d-block text-secondary mb-1"></i>
                            No beneficiaries in scope yet.
                            @can('beneficiaries.create')
                                <a href="{{ route('beneficiaries.create') }}">Register the first one</a>.
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($beneficiaries->hasPages())
            <div class="px-3 py-3 border-top">
                {{ $beneficiaries->links() }}
            </div>
        @endif
    </x-card>
@endsection
