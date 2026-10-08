{{-- Shared identity/location/programme fields for create + edit.
     Expects: $beneficiary (?Beneficiary), $programmes, $states,
     $localGovernmentAreas, $wards, $communities. --}}

<x-card title="Identity" subtitle="Who this record is about" class="mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <x-form-input name="first_name" label="First name" required
                :value="$beneficiary->first_name ?? null" />
        </div>
        <div class="col-12 col-md-4">
            <x-form-input name="middle_name" label="Middle name"
                :value="$beneficiary->middle_name ?? null" />
        </div>
        <div class="col-12 col-md-4">
            <x-form-input name="last_name" label="Surname" required
                :value="$beneficiary->last_name ?? null" />
        </div>
        <div class="col-6 col-md-3">
            <label for="sex" class="form-label fw-semibold small">Sex <span class="text-danger">*</span></label>
            <select name="sex" id="sex" class="form-select @error('sex') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach (\App\Enums\Sex::cases() as $option)
                    <option value="{{ $option->value }}"
                        @selected(old('sex', ($beneficiary->sex ?? null)?->value) === $option->value)>{{ ucfirst($option->value) }}</option>
                @endforeach
            </select>
            @error('sex')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <x-form-input name="date_of_birth" label="Date of birth" type="date"
                :value="old('date_of_birth', isset($beneficiary) && $beneficiary->date_of_birth ? $beneficiary->date_of_birth->format('Y-m-d') : null)" />
        </div>
        <div class="col-6 col-md-3">
            <label for="date_of_birth_accuracy" class="form-label fw-semibold small">Date accuracy</label>
            <select name="date_of_birth_accuracy" id="date_of_birth_accuracy" class="form-select @error('date_of_birth_accuracy') is-invalid @enderror">
                <option value="">Unknown</option>
                @foreach (\App\Enums\DateAccuracy::cases() as $option)
                    <option value="{{ $option->value }}"
                        @selected(old('date_of_birth_accuracy', ($beneficiary->date_of_birth_accuracy ?? null)?->value) === $option->value)>{{ ucfirst($option->value) }}</option>
                @endforeach
            </select>
            @error('date_of_birth_accuracy')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</x-card>

<x-card title="Placement" subtitle="Programme, household and location" class="mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="programme_id" class="form-label fw-semibold small">Programme <span class="text-danger">*</span></label>
            <select name="programme_id" id="programme_id" class="form-select @error('programme_id') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach ($programmes as $programme)
                    <option value="{{ $programme->id }}"
                        @selected((int) old('programme_id', $beneficiary->programme_id ?? 0) === (int) $programme->id)>{{ $programme->name }} ({{ $programme->code }})</option>
                @endforeach
            </select>
            @error('programme_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <x-form-input name="registration_date" label="Registration date" type="date" required
                :value="old('registration_date', isset($beneficiary) && $beneficiary->registration_date ? $beneficiary->registration_date->format('Y-m-d') : date('Y-m-d'))" />
        </div>
        <div class="col-6 col-md-3">
            <x-form-input name="household_id" label="Household ID (optional)" type="number"
                help="Link to an existing household record."
                :value="$beneficiary->household_id ?? null" />
        </div>
        <div class="col-6 col-md-3">
            <label for="state_id" class="form-label fw-semibold small">State</label>
            <select name="state_id" id="state_id" class="form-select @error('state_id') is-invalid @enderror">
                <option value="">Select…</option>
                @foreach ($states as $state)
                    <option value="{{ $state->id }}"
                        @selected((int) old('state_id', $beneficiary->state_id ?? 0) === (int) $state->id)>{{ $state->name }}</option>
                @endforeach
            </select>
            @error('state_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="local_government_area_id" class="form-label fw-semibold small">LGA</label>
            <select name="local_government_area_id" id="local_government_area_id" class="form-select @error('local_government_area_id') is-invalid @enderror">
                <option value="">Select…</option>
                @foreach ($localGovernmentAreas as $lga)
                    <option value="{{ $lga->id }}"
                        @selected((int) old('local_government_area_id', $beneficiary->local_government_area_id ?? 0) === (int) $lga->id)>{{ $lga->name }}</option>
                @endforeach
            </select>
            @error('local_government_area_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="ward_id" class="form-label fw-semibold small">Ward</label>
            <select name="ward_id" id="ward_id" class="form-select @error('ward_id') is-invalid @enderror">
                <option value="">Select…</option>
                @foreach ($wards as $ward)
                    <option value="{{ $ward->id }}"
                        @selected((int) old('ward_id', $beneficiary->ward_id ?? 0) === (int) $ward->id)>{{ $ward->name }}</option>
                @endforeach
            </select>
            @error('ward_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="community_id" class="form-label fw-semibold small">Community</label>
            <select name="community_id" id="community_id" class="form-select @error('community_id') is-invalid @enderror">
                <option value="">Select…</option>
                @foreach ($communities as $community)
                    <option value="{{ $community->id }}"
                        @selected((int) old('community_id', $beneficiary->community_id ?? 0) === (int) $community->id)>{{ $community->name }}</option>
                @endforeach
            </select>
            @error('community_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</x-card>

<x-card title="Notes" subtitle="Anything the next caseworker must know" class="mb-4">
    <label for="notes" class="form-label fw-semibold small">Case notes</label>
    <textarea name="notes" id="notes" rows="3"
        class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $beneficiary->notes ?? '') }}</textarea>
    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</x-card>
