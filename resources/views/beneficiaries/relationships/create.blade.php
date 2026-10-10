@extends('layouts.app')

@section('title', 'Record Relationship — ORWeMS')

@section('main')
    <x-page-header
        title="Record Relationship"
        subtitle="Who is {{ $beneficiary->first_name }}’s family, guardian or caregiver?"
        :breadcrumbs="['Home' => url('/'), 'Beneficiaries' => route('beneficiaries.index'), $beneficiary->beneficiary_ref => route('beneficiaries.show', $beneficiary), 'Record relationship' => null]"
    />

    <x-alert type="info" class="mb-4">
        <div>
            A relationship points at exactly one counterpart — a person, a new
            person you describe here, or another registered child. Recorded links
            stay in the record; ending one later is preferred to deleting it.
        </div>
    </x-alert>

    <form method="POST" action="{{ route('beneficiaries.relationships.store', $beneficiary) }}" novalidate>
        @csrf

        <x-card title="Relationship" class="mb-4">
            <div class="mb-3">
                <label for="relationship_type_id" class="form-label fw-semibold small">
                    Kind of relationship <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <select name="relationship_type_id" id="relationship_type_id"
                        class="form-select @error('relationship_type_id') is-invalid @enderror" required>
                    <option value="">Select…</option>
                    @foreach ($relationshipTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('relationship_type_id') === $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
                @error('relationship_type_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">
                    The relationship points to <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="related_party_type" id="party_existing_person"
                           value="existing_person" @checked(old('related_party_type', 'existing_person') === 'existing_person')>
                    <label class="form-check-label" for="party_existing_person">An existing person on a household roster</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="related_party_type" id="party_new_person"
                           value="new_person" @checked(old('related_party_type') === 'new_person')>
                    <label class="form-check-label" for="party_new_person">A person not yet recorded — describe them here</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="related_party_type" id="party_existing_beneficiary"
                           value="existing_beneficiary" @checked(old('related_party_type') === 'existing_beneficiary')>
                    <label class="form-check-label" for="party_existing_beneficiary">Another registered child (e.g. a sibling)</label>
                </div>
                @error('related_party_type')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label for="related_person_id" class="form-label fw-semibold small">Existing person</label>
                        <select name="related_person_id" id="related_person_id"
                                class="form-select @error('related_person_id') is-invalid @enderror">
                            <option value="">Select…</option>
                            @foreach ($persons as $person)
                                <option value="{{ $person->id }}" @selected((int) old('related_person_id') === $person->id)>
                                    {{ $person->last_name }}, {{ $person->first_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('related_person_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label for="related_beneficiary_id" class="form-label fw-semibold small">Existing child</label>
                        <select name="related_beneficiary_id" id="related_beneficiary_id"
                                class="form-select @error('related_beneficiary_id') is-invalid @enderror">
                            <option value="">Select…</option>
                            @foreach ($counterpartBeneficiaries as $child)
                                <option value="{{ $child->id }}" @selected((int) old('related_beneficiary_id') === $child->id)>
                                    {{ $child->beneficiary_ref }} — {{ $child->first_name }} {{ $child->last_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('related_beneficiary_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </x-card>

        <x-card title="If the person is new — their details" class="mb-4">
            <p class="small text-muted">Creating the person also needs the “create persons” capability; a missing capability is refused outright.</p>
            <div class="row">
                <div class="col-12 col-md-4">
                    <x-form-input name="first_name" label="First name" :value="old('first_name')" maxlength="100" />
                </div>
                <div class="col-12 col-md-4">
                    <x-form-input name="last_name" label="Last name" :value="old('last_name')" maxlength="100" />
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label for="sex" class="form-label fw-semibold small">Sex</label>
                        <select name="sex" id="sex" class="form-select @error('sex') is-invalid @enderror">
                            <option value="">Prefer not to say</option>
                            @foreach (\App\Enums\Sex::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('sex') === $option->value)>
                                    {{ ucfirst($option->value) }}
                                </option>
                            @endforeach
                        </select>
                        @error('sex')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-4">
                    <x-form-input name="date_of_birth" label="Date of birth" type="date" :value="old('date_of_birth')" />
                </div>
                <div class="col-12 col-md-8">
                    <div class="mb-3">
                        <label for="household_id" class="form-label fw-semibold small">Household</label>
                        <select name="household_id" id="household_id"
                                class="form-select @error('household_id') is-invalid @enderror">
                            <option value="">Join {{ $beneficiary->first_name }}’s household</option>
                            @foreach ($households as $household)
                                <option value="{{ $household->id }}" @selected((int) old('household_id') === $household->id)>
                                    {{ $household->household_code }} — {{ $household->household_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('household_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </x-card>

        <x-card title="When" class="mb-4">
            <x-form-input name="start_date" label="Relationship start date" type="date"
                          :value="old('start_date', now()->toDateString())" required />
            <div class="mb-0">
                <label for="notes" class="form-label fw-semibold small">Notes</label>
                <textarea name="notes" id="notes" rows="2"
                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </x-card>

        <div class="d-flex gap-2">
            <x-button type="submit" icon="bi-people">
                Record Relationship
            </x-button>
            <x-button variant="outline-secondary" :href="route('beneficiaries.show', $beneficiary)">
                Cancel
            </x-button>
        </div>
    </form>
@endsection