<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VerifiesCareScope;
use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a NEW care placement for a beneficiary (Phase 5).
 *
 * Field-shape rules first; then the cross-field checks that carry the real
 * authorization weight for this workflow:
 *
 *  - the arrangement kind must exist AND be active (withdrawn kinds keep
 *    their history but can no longer be chosen);
 *  - the beneficiary must have NO active placement — a second open row is
 *    refused with the instruction to use the Change flow, and the database
 *    unique index over the generated active_flag remains the hard stop;
 *  - the household and the caregiver must sit in areas this operator is
 *    scoped to (VerifiesCareScope), because a placement form must not
 *    become a way to attach out-of-area records.
 *
 * The controller authorizes `create` on the placement policy (permission AND
 * beneficiary scope); this request adds the INPUT-level scope checks, so a
 * denial names the offending field instead of being a bare 403.
 */
class StorePlacementRequest extends FormRequest
{
    use VerifiesCareScope;

    /**
     * Authorization runs in the controller through PlacementPolicy, exactly
     * like Phase 4's StoreBeneficiaryRequest; this request owns validation.
     */
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
            'placement_reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                // Field-shape failures already reported; cross-field checks
                // would read invalid ids and produce misleading errors.
                return;
            }

            $data = $validator->validated();

            $this->assertNoActivePlacement($validator);
            $this->assertHouseholdInScope($data, $validator);
            $this->assertPersonInScope($data['caregiver_person_id'] ?? null, 'caregiver_person_id', $validator);
        });
    }

    /**
     * One child, one current placement — enforced here with a readable
     * message before the database constraint has to say it with a
     * constraint violation.
     */
    private function assertNoActivePlacement($validator): void
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        if ($beneficiary->placements()->whereNull('end_date')->exists()) {
            $validator->errors()->add(
                'start_date',
                'This beneficiary already has an active placement. Close it first — or use the Change Placement flow, which records both halves together.'
            );
        }
    }
}