{{--
    The placement fields shared by create / edit / change (Phase 5).

    `placement` may be null (create/change) — old() falls back to the model.
    Closing fields deliberately live on their own screens (close/change), never
    here: this partial is the "substance of an arrangement" and cannot smuggle
    an end date into an amendment.
--}}
@php($placement = $placement ?? null)

<div class="row">
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="care_arrangement_type_id" class="form-label fw-semibold small">
                Arrangement type <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select name="care_arrangement_type_id" id="care_arrangement_type_id"
                    class="form-select @error('care_arrangement_type_id') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach ($arrangementTypes as $type)
                    <option value="{{ $type->id }}"
                        @selected((int) old('care_arrangement_type_id', $placement?->care_arrangement_type_id) === $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
            @error('care_arrangement_type_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12 col-md-6">
        <x-form-input name="start_date" label="Start date" type="date"
                      :value="$placement?->start_date?->toDateString()" required />
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="household_id" class="form-label fw-semibold small">Household</label>
            <select name="household_id" id="household_id"
                    class="form-select @error('household_id') is-invalid @enderror">
                <option value="">No household / not applicable</option>
                @foreach ($households as $household)
                    <option value="{{ $household->id }}"
                        @selected((int) old('household_id', $placement?->household_id) === $household->id)>
                        {{ $household->household_code }} — {{ $household->household_name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text small">Only households in your area are offered; a submitted household outside your scope is refused.</div>
            @error('household_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="caregiver_person_id" class="form-label fw-semibold small">Primary caregiver</label>
            <select name="caregiver_person_id" id="caregiver_person_id"
                    class="form-select @error('caregiver_person_id') is-invalid @enderror">
                <option value="">Not recorded</option>
                @foreach ($persons as $person)
                    <option value="{{ $person->id }}"
                        @selected((int) old('caregiver_person_id', $placement?->caregiver_person_id) === $person->id)>
                        {{ $person->last_name }}, {{ $person->first_name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text small">Only persons living in households within your area are offered.</div>
            @error('caregiver_person_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<x-form-input name="placement_reason" label="Reason for this placement"
              :value="$placement?->placement_reason" required maxlength="255"
              help="Why the child is in this arrangement — the question any reviewer will ask first." />

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold small">Notes</label>
    <textarea name="notes" id="notes" rows="3"
              class="form-control @error('notes') is-invalid @enderror"
              placeholder="Anything a colleague taking over this case would need to know">{{ old('notes', $placement?->notes) }}</textarea>
    @error('notes')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
