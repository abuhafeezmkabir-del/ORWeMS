{{--
    The education enrolment fields shared by create / edit / change (Phase 6A).

    `enrolment` may be null (create/change) — old() falls back to the model.
    Closing fields deliberately live on their own screens (close/change), never
    here: this partial is the "substance of an enrolment" and cannot smuggle an
    end date into an amendment.

    Unlike the placement form there is no household/caregiver scope dropdown:
    an education enrolment references only global reference rows (level,
    optional institution) plus the beneficiary, whose scope the policy already
    enforces. Scope is therefore not a per-field concern on this form.
--}}
@php($enrolment = $enrolment ?? null)

<div class="row">
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="education_level_id" class="form-label fw-semibold small">
                Education level <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select name="education_level_id" id="education_level_id"
                    class="form-select @error('education_level_id') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}"
                        @selected((int) old('education_level_id', $enrolment?->education_level_id) === $level->id)>
                        {{ $level->name }}
                    </option>
                @endforeach
            </select>
            @error('education_level_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12 col-md-6">
        <x-form-input name="start_date" label="Enrolment / start date" type="date"
                      :value="$enrolment?->start_date?->toDateString()" required />
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-6">
        <x-form-input name="institution_name" label="School / institution name"
                      :value="$enrolment?->institution_name" required maxlength="255"
                      help="The school as you record it — always kept, even if the institution is not in the structured list." />
    </div>
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="institution_id" class="form-label fw-semibold small">Link to a known institution (optional)</label>
            <select name="institution_id" id="institution_id"
                    class="form-select @error('institution_id') is-invalid @enderror">
                <option value="">Not linked / not in the list</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}"
                        @selected((int) old('institution_id', $enrolment?->institution_id) === $institution->id)>
                        {{ $institution->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text small">Optional structured reference; the name above is the record of where the child studied.</div>
            @error('institution_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<x-form-input name="class_or_year" label="Class / grade or year of study"
              :value="$enrolment?->class_or_year" maxlength="100"
              help="e.g. “Primary 3”, “JSS 2”, “Year 1”. Leave blank if not applicable." />

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold small">Notes</label>
    <textarea name="notes" id="notes" rows="3"
              class="form-control @error('notes') is-invalid @enderror"
              placeholder="Education-related casework notes a colleague taking over this case would need to know">{{ old('notes', $enrolment?->notes) }}</textarea>
    @error('notes')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
