@extends('layouts.app')

@section('title', 'Beneficiary Case File — ORWeMS')

@section('main')
    <x-page-header
        title="{{ $beneficiary->first_name }} {{ $beneficiary->last_name }}"
        subtitle="Reference {{ $beneficiary->beneficiary_ref }}"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => null]"
    >
        @can('update', $beneficiary)
            <x-button variant="outline-secondary" size="sm" icon="bi-pencil" :href="route('beneficiaries.edit', $beneficiary)">
                Amend
            </x-button>
        @endcan
        @can('archive', $beneficiary)
            <x-button variant="outline-danger" size="sm" icon="bi-archive" :href="route('beneficiaries.archive', $beneficiary)">
                Archive
            </x-button>
        @endcan
    </x-page-header>

    @if ($beneficiary->status === \App\Enums\BeneficiaryStatus::Active)
        <x-alert type="success" class="mb-4">
            <div><strong>Verified.</strong> This registration has been confirmed against evidence.</div>
        </x-alert>
    @elseif ($beneficiary->status === \App\Enums\BeneficiaryStatus::Registered)
        <x-alert type="warning" class="mb-4">
            <div>
                <strong>Awaiting verification.</strong> This record was registered but not yet confirmed.
                @can('verify', $beneficiary)
                    <form method="POST" action="{{ route('beneficiaries.verify', $beneficiary) }}" class="d-inline ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-patch-check me-1"></i>Verify now
                        </button>
                    </form>
                @endcan
            </div>
        </x-alert>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <x-card title="Identity" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Reference</dt>
                    <dd class="col-sm-8"><code>{{ $beneficiary->beneficiary_ref }}</code></dd>
                    <dt class="col-sm-4 text-muted">Full name</dt>
                    <dd class="col-sm-8 fw-semibold text-dark">{{ trim($beneficiary->first_name.' '.($beneficiary->middle_name ? $beneficiary->middle_name.' ' : '').$beneficiary->last_name) }}</dd>
                    <dt class="col-sm-4 text-muted">Sex</dt>
                    <dd class="col-sm-8">{{ ucfirst($beneficiary->sex->value) }}</dd>
                    <dt class="col-sm-4 text-muted">Date of birth</dt>
                    <dd class="col-sm-8">{{ $beneficiary->date_of_birth?->format('d M Y') ?? 'Not recorded' }} <span class="text-muted">({{ ucfirst(($beneficiary->date_of_birth_accuracy?->value) ?? 'unknown') }})</span></dd>
                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8">{{ ucfirst($beneficiary->status->value) }}</dd>
                    <dt class="col-sm-4 text-muted">Registered</dt>
                    <dd class="col-sm-8">{{ $beneficiary->registration_date?->format('d M Y') }}</dd>
                </dl>
            </x-card>

            <x-card title="Location" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">State</dt>
                    <dd class="col-sm-8">{{ $beneficiary->state->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">LGA</dt>
                    <dd class="col-sm-8">{{ $beneficiary->localGovernmentArea->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Ward</dt>
                    <dd class="col-sm-8">{{ $beneficiary->ward->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Community</dt>
                    <dd class="col-sm-8">{{ $beneficiary->community->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Household</dt>
                    <dd class="col-sm-8">{{ $beneficiary->household ? ($beneficiary->household->household_code.' — '.$beneficiary->household->household_name) : '—' }}</dd>
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-5">
            <x-card title="Programme" class="mb-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Programme</dt>
                    <dd class="col-sm-8">{{ $beneficiary->programme->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Code</dt>
                    <dd class="col-sm-8"><code>{{ $beneficiary->programme->code ?? '—' }}</code></dd>
                </dl>
            </x-card>

            @if ($beneficiary->notes)
                <x-card title="Case notes" class="mb-4">
                    <p class="small mb-0">{{ $beneficiary->notes }}</p>
                </x-card>
            @endif

            <x-card title="Record history" subtitle="Verification and lifecycle, not case content">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Registered</span>
                        <span>{{ $beneficiary->created_at?->format('d M Y, H:i') }}</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Last amended</span>
                        <span>{{ $beneficiary->updated_at?->format('d M Y, H:i') }}</span>
                    </li>
                </ul>
            </x-card>
        </div>
    </div>

    {{-- Phase 5 — care, family & placement context. Every section renders only
         when the controller loaded it, i.e. only for operators whose
         permission AND scope reach the underlying rows. --}}
    <div class="row g-4 mt-1">
        @isset($currentPlacement)
            <div class="col-12 col-lg-7">
                <x-card title="Current care arrangement">
                    <dl class="row mb-3 small">
                        <dt class="col-sm-4 text-muted">Arrangement</dt>
                        <dd class="col-sm-8 fw-semibold text-dark">{{ $currentPlacement->careArrangementType?->name ?? 'Not recorded' }}</dd>
                        <dt class="col-sm-4 text-muted">Since</dt>
                        <dd class="col-sm-8">{{ $currentPlacement->start_date?->format('d M Y') }}</dd>
                        <dt class="col-sm-4 text-muted">Household</dt>
                        <dd class="col-sm-8">
                            @if ($currentPlacement->household)
                                <code>{{ $currentPlacement->household->household_code }}</code> {{ $currentPlacement->household->household_name }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4 text-muted">Primary caregiver</dt>
                        <dd class="col-sm-8">
                            {{ trim(($currentPlacement->caregiverPerson?->first_name ?? '').' '.($currentPlacement->caregiverPerson?->last_name ?? '')) ?: '—' }}
                        </dd>
                        <dt class="col-sm-4 text-muted">Reason recorded</dt>
                        <dd class="col-sm-8">{{ $currentPlacement->placement_reason ?? '—' }}</dd>
                    </dl>
                    <div class="d-flex gap-2">
                        @can('close', $currentPlacement)
                            <x-button variant="outline-warning" size="sm" icon="bi-arrow-repeat" :href="route('beneficiaries.placements.change', $beneficiary)">
                                Change placement
                            </x-button>
                            <x-button variant="outline-secondary" size="sm" icon="bi-door-closed" :href="route('beneficiaries.placements.close', [$beneficiary, $currentPlacement])">
                                Close placement
                            </x-button>
                        @endcan
                        @can('update', $currentPlacement)
                            <x-button variant="outline-secondary" size="sm" icon="bi-pencil" :href="route('beneficiaries.placements.edit', [$beneficiary, $currentPlacement])">
                                Amend
                            </x-button>
                        @endcan
                    </div>
                </x-card>
            </div>
        @elseisset($placements)
            <div class="col-12 col-lg-7">
                <x-card title="Current care arrangement">
                    <p class="small text-muted mb-3">
                        No active placement is recorded for this child.
                    </p>
                    @can('create', [\App\Models\Placement::class, $beneficiary])
                        <x-button variant="outline-success" size="sm" icon="bi-house-add" :href="route('beneficiaries.placements.create', $beneficiary)">
                            Record placement
                        </x-button>
                    @endcan
                </x-card>
            </div>
        @endisset

        @isset($placements)
            <div class="col-12 col-lg-5">
                <x-card title="Placement history" subtitle="Closed arrangements remain — history is evidence">
                    @if ($placements->isEmpty())
                        <p class="small text-muted mb-0">No placements have been recorded.</p>
                    @else
                        <ul class="list-group list-group-flush small">
                            @foreach ($placements as $placement)
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-semibold text-dark">{{ $placement->careArrangementType?->name ?? '—' }}</span>
                                        <x-badge :variant="$placement->isActive() ? 'success' : 'secondary'">
                                            {{ $placement->isActive() ? 'Current' : 'Previous' }}
                                        </x-badge>
                                    </div>
                                    <div class="text-muted">
                                        {{ $placement->start_date?->format('d M Y') }} —
                                        {{ $placement->end_date?->format('d M Y') ?? 'present' }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3">
                            <x-button variant="outline-secondary" size="sm" icon="bi-clock-history" :href="route('beneficiaries.placements.index', $beneficiary)">
                                Full placement history
                            </x-button>
                        </div>
                    @endif
                </x-card>
            </div>
        @endisset

        @isset($enrolments)
            <div class="col-12 col-lg-5">
                <x-card title="Education history" subtitle="Where this child studies now, and where they have studied" class="mb-4">
                    @if ($enrolments->isEmpty())
                        <p class="small text-muted mb-3">No education records have been recorded for this child.</p>
                    @else
                        <ul class="list-group list-group-flush small mb-3">
                            @foreach ($enrolments as $enrolment)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            {{ $enrolment->institution_name }}
                                            <span class="fw-normal text-muted">— {{ $enrolment->educationLevel?->name ?? '—' }}</span>
                                        </div>
                                        <div class="text-muted fst-italic">
                                            since {{ $enrolment->start_date?->format('d M Y') }}
                                            @if ($enrolment->end_date !== null)
                                                — ended {{ $enrolment->end_date->format('d M Y') }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($enrolment->isCurrent())
                                        <x-badge variant="success" :pill="true">Current</x-badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="d-flex gap-2">
                        @can('viewFor', [\App\Models\EducationEnrolment::class, $beneficiary])
                            <x-button variant="outline-primary" size="sm" icon="bi-mortarboard" :href="route('beneficiaries.education.index', $beneficiary)">
                                Full education history
                            </x-button>
                        @endcan
                        @can('create', [\App\Models\EducationEnrolment::class, $beneficiary])
                            <x-button variant="outline-success" size="sm" icon="bi-mortarboard-fill" :href="route('beneficiaries.education.create', $beneficiary)">
                                Record enrolment
                            </x-button>
                        @endcan
                    </div>
                </x-card>
            </div>
        @endisset

        @isset($relationships)
            <div class="col-12 col-lg-7">
                <x-card title="Family & guardians" subtitle="Recorded relationships for this child">
                    @if ($relationships->isEmpty())
                        <p class="small text-muted mb-3">No relationships have been recorded.</p>
                    @else
                        <ul class="list-group list-group-flush small mb-3">
                            @foreach ($relationships as $relationship)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-semibold text-dark">{{ $relationship->relationshipType?->name ?? '—' }}</span>
                                        <div class="text-muted">
                                            @if ((int) $relationship->related_beneficiary_id === (int) $beneficiary->getKey())
                                                {{ trim(($relationship->person?->first_name ?? '').' '.($relationship->person?->last_name ?? '')) ?: 'Unidentified person' }}
                                            @elseif ($relationship->related_beneficiary_id !== null)
                                                {{ trim(($relationship->relatedBeneficiary?->first_name ?? '').' '.($relationship->relatedBeneficiary?->last_name ?? '')) }}
                                                <code>{{ $relationship->relatedBeneficiary?->beneficiary_ref }}</code>
                                            @else
                                                {{ trim(($relationship->relatedPerson?->first_name ?? '').' '.($relationship->relatedPerson?->last_name ?? '')) }}
                                            @endif
                                        </div>
                                        <div class="text-muted fst-italic">
                                            since {{ $relationship->start_date?->format('d M Y') }}
                                            @if ($relationship->end_date !== null)
                                                — ended {{ $relationship->end_date->format('d M Y') }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($relationship->end_date === null)
                                        @can('update', $relationship)
                                            <x-button variant="outline-secondary" size="sm" icon="bi-dash-circle" :href="route('beneficiaries.relationships.end', [$beneficiary, $relationship])">
                                                End
                                            </x-button>
                                        @endcan
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @can('create', [\App\Models\PersonRelationship::class, $beneficiary])
                        <x-button variant="outline-success" size="sm" icon="bi-people" :href="route('beneficiaries.relationships.create', $beneficiary)">
                            Record relationship
                        </x-button>
                    @endcan
                </x-card>
            </div>
        @endisset

        @isset($household)
            <div class="col-12 col-lg-5">
                <x-card title="Household members" subtitle="{{ $household->household_code }} — {{ $household->household_name }}">
                    @if (($household->members ?? collect())->isEmpty())
                        <p class="small text-muted mb-0">No roster members recorded.</p>
                    @else
                        <ul class="list-group list-group-flush small mb-0">
                            @foreach ($household->members as $member)
                                <li class="list-group-item px-0 d-flex justify-content-between">
                                    <span>
                                        {{ trim($member->first_name.' '.($member->last_name ? $member->last_name : '')) }}
                                        @if ($member->id === $beneficiary->person_id)
                                            <x-badge variant="info" :pill="true">This child</x-badge>
                                        @endif
                                    </span>
                                    <span>
                                        @if ($member->is_guardian)
                                            <x-badge variant="secondary" :pill="true">Guardian</x-badge>
                                        @endif
                                        @if ($member->is_caregiver)
                                            <x-badge variant="secondary" :pill="true">Caregiver</x-badge>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        @endisset
    </div>
@endsection
