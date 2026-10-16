{{--
    The health record fields shared by create / edit (Phase 6B).

    `record` may be null (create) — old() falls back to the model.
    Closing fields deliberately live on their own screen (close), never
    here: this partial is the "substance of a record" and cannot smuggle an
    end date into an amendment.

    There is no placement-style scope dropdown: a health record references
    only a global reference row (concern) plus the beneficiary, whose scope
    the policy already enforces. Scope is therefore not a per-field concern
    on this form.
--}}
@php($record = $record ?? null)

<div class="row">
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="health_concern_id" class="form-label fw-semibold small">
                Health concern <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select name="health_concern_id" id="health_concern_id"
                    class="form-select @error('health_concern_id') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach ($concerns as $concern)
                    <option value="{{ $concern->id }}"
                        @selected((int) old('health_concern_id', $record?->health_concern_id) === $concern->id)>
                        {{ $concern->name }}
                    </option>
                @endforeach
            </select>
            @error('health_concern_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12 col-md-6">
        <x-form-input name="record_date" label="Record date" type="date"
                      :value="$record?->record_date?->toDateString()" required />
    </div>
</div>

<x-form-input name="summary" label="Casework summary"
              :value="$record?->summary" required maxlength="500"
              help="A concise casework summary — what is being tracked and who needs to act. No diagnoses or clinical detail." />

<div class="row">
    <div class="col-12 col-md-6">
        <div class="mb-3">
            <label for="follow_up_status" class="form-label fw-semibold small">
                Follow-up status <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select name="follow_up_status" id="follow_up_status"
                    class="form-select @error('follow_up_status') is-invalid @enderror" required>
                @foreach (['none' => 'No follow-up needed', 'pending' => 'Follow-up pending', 'done' => 'Follow-up done'] as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('follow_up_status', $record?->follow_up_status ?? 'none') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('follow_up_status')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12 col-md-6">
        <x-form-input name="referral_to" label="Referred to (only when a referral is tracked)"
                      :value="$record?->referral_to" maxlength="255"
                      help="The facility or service, where applicable. Leave blank unless follow-up is pending or done." />
    </div>
</div>

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold small">Notes</label>
    <textarea name="notes" id="notes" rows="3"
              class="form-control @error('notes') is-invalid @enderror"
              placeholder="Casework notes a colleague taking over this case would need to know">{{ old('notes', $record?->notes) }}</textarea>
    @error('notes')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
