<?php

namespace App\Http\Requests;

use App\Models\EducationEnrolment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing an education enrolment (Phase 6A).
 *
 * Closing is the one-way step in the workflow: it sets the end date and the
 * row stays forever as history. Two rules carry the meaning of that step:
 *
 *  - the end date cannot be before the start date (an enrolment cannot have
 *    ended before it began) — checked against THIS enrolment's start, which is
 *    a different field from anything in the payload, hence the after() hook
 *    rather than an `after_or_equal` rule;
 *  - the end date cannot be in the future: "closed" means the child is not
 *    enrolled there now. A future departure is recorded when it happens —
 *    approving today for a change next term would put a child's present
 *    schooling out of date for the whole interval, which is the opposite of
 *    what an education record is for.
 *
 * closure_reason is optional (not every close has a story beyond the routine),
 * but when present it is stored on the row and the audit event records who
 * closed and when either way.
 */
class CloseEducationRequest extends FormRequest
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
            'end_date' => ['required', 'date', 'before_or_equal:today'],
            'closure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            /** @var EducationEnrolment|null $enrolment */
            $enrolment = $this->route('enrolment');

            if (! $enrolment instanceof EducationEnrolment || $enrolment->start_date === null) {
                return;
            }

            $end = \Illuminate\Support\Carbon::parse($this->validated()['end_date']);

            if ($end->lt($enrolment->start_date)) {
                $validator->errors()->add(
                    'end_date',
                    sprintf(
                        'The end date cannot be before the enrolment started (%s).',
                        $enrolment->start_date->format('d M Y')
                    )
                );
            }
        });
    }
}
