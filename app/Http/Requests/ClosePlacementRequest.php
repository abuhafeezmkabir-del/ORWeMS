<?php

namespace App\Http\Requests;

use App\Models\Placement;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing a care placement (Phase 5).
 *
 * Closing is the one-way step in the workflow: it sets the end date, flips
 * the row to Closed, and the row stays forever as history. Two rules carry
 * the meaning of that step:
 *
 *  - the end date cannot be before the start date (a placement cannot have
 *    ended before it began) — checked against THIS placement's start, which
 *    is a different field from anything in the payload, hence the after()
 *    hook rather than an `after_or_equal` rule;
 *  - the end date cannot be in the future: "closed" means the child is not
 *    there now. A future departure is recorded when it happens — approving
 *    today for a change next month would put a child's present location out
 *    of date for the whole interval, which is the opposite of what a care
 *    record is for.
 *
 * closure_reason is optional ("where required" — not every close has a
 * story beyond the routine), but when present it is stored on the row and
 * the audit event records who closed and when either way.
 */
class ClosePlacementRequest extends FormRequest
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

            /** @var Placement|null $placement */
            $placement = $this->route('placement');

            if (! $placement instanceof Placement || $placement->start_date === null) {
                return;
            }

            $end = \Illuminate\Support\Carbon::parse($this->validated()['end_date']);

            if ($end->lt($placement->start_date)) {
                $validator->errors()->add(
                    'end_date',
                    sprintf(
                        'The end date cannot be before the placement started (%s).',
                        $placement->start_date->format('d M Y')
                    )
                );
            }
        });
    }
}