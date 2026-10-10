<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a NEW education enrolment for a beneficiary (Phase 6A).
 *
 * Field-shape rules first; then the cross-field checks that carry the real
 * authorization weight for this workflow:
 *
 *  - the education level must exist AND be active (withdrawn levels keep their
 *    history but can no longer be chosen);
 *  - an optional institution, when named, must exist and be active;
 *  - the beneficiary must have NO current enrolment — a second open row is
 *    refused with the instruction to use the Change flow, and the database
 *    unique index over the generated active_flag remains the hard stop.
 *
 * There is deliberately NO household/caregiver scope check here, unlike
 * Phase 5's placement requests: an education enrolment references only the
 * beneficiary (whose scope the POLICY already enforces) plus global reference
 * rows, not any scoped record the form could smuggle in. Deny-by-default scope
 * on the beneficiary itself is therefore the whole of the WHERE question.
 *
 * The controller authorizes `create` on the policy (permission AND beneficiary
 * scope); this request adds the INPUT-level duplicate check, so a denial names
 * the offending field instead of being a bare 403.
 */
class StoreEducationRequest extends FormRequest
{
    /**
     * Authorization runs in the controller through EducationEnrolmentPolicy,
     * exactly like Phase 5's StorePlacementRequest; this request owns
     * validation.
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
            'education_level_id' => [
                'required', 'integer',
                Rule::exists('education_levels', 'id')->where('is_active', true),
            ],
            'institution_id' => [
                'nullable', 'integer',
                Rule::exists('institutions', 'id')->where('is_active', true),
            ],
            'institution_name' => ['required', 'string', 'max:255'],
            'class_or_year' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
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

            $this->assertNoCurrentEnrolment($validator);
        });
    }

    /**
     * One child, one current enrolment — enforced here with a readable message
     * before the database constraint has to say it with a constraint
     * violation.
     */
    private function assertNoCurrentEnrolment($validator): void
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        if ($beneficiary->enrolments()->whereNull('end_date')->exists()) {
            $validator->errors()->add(
                'start_date',
                'This beneficiary already has a current enrolment. Close it first — or use the Change Enrolment flow, which records both halves together.'
            );
        }
    }
}
