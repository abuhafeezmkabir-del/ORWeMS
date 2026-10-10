@extends('layouts.app')

@section('title', 'Education History — ORWeMS')

@section('main')
    <x-page-header
        title="Education History"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }} — {{ $beneficiary->first_name }} {{ $beneficiary->last_name }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Education' => null]"
    >
        @can('create', [\App\Models\EducationEnrolment::class, $beneficiary])
            <x-button variant="outline-success" size="sm" icon="bi-mortarboard" :href="route('beneficiaries.education.create', $beneficiary)">
                Record enrolment
            </x-button>
        @endcan
    </x-page-header>

    @if ($currentEnrolment === null)
        <x-alert type="info" class="mb-4">
            <div>
                No current enrolment is recorded for this child
                @can('create', [\App\Models\EducationEnrolment::class, $beneficiary])
                    — <a href="{{ route('beneficiaries.education.create', $beneficiary) }}" class="alert-link">record one</a>.
                @endcan
            </div>
        </x-alert>
    @else
        <x-alert type="success" class="mb-4">
            <div>
                <strong>Currently enrolled:</strong>
                {{ $currentEnrolment->institution_name }}
                ({{ $currentEnrolment->educationLevel?->name ?? '—' }})
                since {{ $currentEnrolment->start_date?->format('d M Y') }}.
                @can('close', $currentEnrolment)
                    <a href="{{ route('beneficiaries.education.change', $beneficiary) }}" class="alert-link">Change</a>
                    or
                    <a href="{{ route('beneficiaries.education.close', [$beneficiary, $currentEnrolment]) }}" class="alert-link">close</a>
                    it when that changes.
                @endcan
            </div>
        </x-alert>
    @endif

    <x-card title="All enrolments" subtitle="Newest first. Closed rows remain — education history is never deleted.">
        @if ($enrolments->isEmpty())
            <p class="small text-muted mb-0">No education records have been recorded for this child.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>School / institution</th>
                            <th>Level</th>
                            <th>Class / year</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enrolments as $enrolment)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $enrolment->institution_name }}</td>
                                <td>{{ $enrolment->educationLevel?->name ?? '—' }}</td>
                                <td>{{ $enrolment->class_or_year ?? '—' }}</td>
                                <td>{{ $enrolment->start_date?->format('d M Y') }}</td>
                                <td>{{ $enrolment->end_date?->format('d M Y') ?? 'present' }}</td>
                                <td>
                                    <x-badge :variant="$enrolment->isCurrent() ? 'success' : 'secondary'">
                                        {{ $enrolment->isCurrent() ? 'Current' : 'Closed' }}
                                    </x-badge>
                                </td>
                                <td class="text-end">
                                    @if ($enrolment->isCurrent())
                                        @can('update', $enrolment)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('beneficiaries.education.edit', [$beneficiary, $enrolment]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('close', $enrolment)
                                            <a class="btn btn-sm btn-outline-warning" href="{{ route('beneficiaries.education.close', [$beneficiary, $enrolment]) }}">
                                                <i class="bi bi-door-closed"></i>
                                            </a>
                                        @endcan
                                    @else
                                        <span class="text-muted fst-italic">
                                            {{ $enrolment->closure_reason ?? 'closed' }}
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
