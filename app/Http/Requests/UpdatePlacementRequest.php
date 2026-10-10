<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VerifiesCareScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Amending the ACTIVE care placement (Phase 5).
 *
 * Deliberately narrower than the store rules: NO end_date and NO
 * closure_reason fields exist here, so an amendment cannot close a
 * placement — closing is placements.close, its own permission, its own
 * screen, its own audit event. Only the arrangement's substance (kind,
 * location, caregiver, start date, reason, notes) is amenable.
 *
 * end_date and status are also not fillable on the model, so even a
 * tampered request that smuggled the fields past validation could not mass-
 * assign them; two independent refusals, as usual.
 */
class UpdatePlacementRequest extends FormRequest
{
    use VerifiesCareScope;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'care_arrangement_type_id' => [
                'required', 'integer',
                Rule::exists('care_arrangement_types', 'id')->where('is_active', true),
            ],
            'household_id' => [
                'nullable', 'integer',
                Rule::exists('households', 'id')->whereNull('deleted_at'),
            ],
            'caregiver_person_id' => [
                'nullable', 'integer',
                Rule::exists('persons', 'id')->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'placement_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $data = $validator->validated();

            $this->assertHouseholdInScope($data, $validator);
            $this->assertPersonInScope($data['caregiver_person_id'] ?? null, 'caregiver_person_id', $validator);
        });
    }
}