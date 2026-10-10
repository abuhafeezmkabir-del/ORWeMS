<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use App\Models\EducationEnrolment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Change Enrolment transaction (Phase 6A): close the current enrolment and
 * open the next one, in one request.
 *
 * This is the ONLY sanctioned way to end one enrolment and begin another.
 * Running close() then store() as two separate requests would leave a window —
 * literal, if the requests are seconds apart, and logical, if the second one
 * fails — where a child has NO current enrolment, which is the state the
 * single-current rule exists to prevent from being mistaken for reality. One
 * request, one transaction: both halves commit or neither does.
 *
 * The payload therefore carries both sides:
 *
 *   current_end_date, current_closure_reason   the closing half
 *   education_level_id, institution_name, ...  the opening half (same rules as
 *                                              StoreEducationRequest)
 *
 * Cross-field ordering is validated here against the CURRENT enrolment, which
 * is not in the payload: the closing end date cannot precede its start, and the
 * opening start cannot precede the closing end — a child does not overlap two
 * schools.
 */
class ChangeEducationRequest extends FormRequest
{
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
            // Closing half. start-date comparison runs in withValidator()
            // against the CURRENT enrolment, which is not in the payload.
            'current_end_date' => ['required', 'date', 'before_or_equal:today'],
            'current_closure_reason' => ['nullable', 'string', 'max:255'],

            // Opening half — identical to StoreEducationRequest.
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
                return;
            }

            $data = $validator->validated();

            // The change route is beneficiary-anchored (…/education/change) and
            // carries no {enrolment} parameter, so the current enrolment is
            // resolved from the beneficiary's CURRENT enrolment — the same row
            // storeChange() will close — rather than from the route.
            $current = $this->currentEnrolment();

            if ($current instanceof EducationEnrolment && $current->start_date !== null) {
                $currentEnd = \Illuminate\Support\Carbon::parse($data['current_end_date']);

                if ($currentEnd->lt($current->start_date)) {
                    $validator->errors()->add(
                        'current_end_date',
                        sprintf(
                            'The end date cannot be before the current enrolment started (%s).',
                            $current->start_date->format('d M Y')
                        )
                    );
                }

                $nextStart = \Illuminate\Support\Carbon::parse($data['start_date']);

                if ($nextStart->lt($currentEnd)) {
                    $validator->errors()->add(
                        'start_date',
                        'The new enrolment cannot start before the current one ends.'
                    );
                }
            }
        });
    }

    /**
     * The beneficiary's current enrolment, resolved from the route beneficiary
     * (the only enrolment the change transaction can close). Mirrors the
     * controller's own resolution so validation and commit agree on the row.
     */
    private function currentEnrolment(): ?EducationEnrolment
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return null;
        }

        return $beneficiary->enrolments()->whereNull('end_date')->first();
    }
}
