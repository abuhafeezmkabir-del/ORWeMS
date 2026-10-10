<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ending a recorded relationship (Phase 5) — the temporal close of a family
 * link, mirroring how placements close.
 *
 * Setting an end date is how the system says "this arrangement stopped",
 * and it is preferred over deleting the row precisely because the row is
 * evidence: the link existed, and now it has stopped. The policy refuses
 * ending an already-ended link (rewriting when a family arrangement stopped
 * is not an operation), and this request pins the date logic: today at the
 * latest, never before the link began.
 */
class EndRelationshipRequest extends FormRequest
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $relationship = $this->route('relationship');

            if ($relationship === null || $relationship->start_date === null) {
                return;
            }

            $end = \Illuminate\Support\Carbon::parse($this->validated()['end_date']);

            if ($end->lt($relationship->start_date)) {
                $validator->errors()->add(
                    'end_date',
                    sprintf(
                        'The end date cannot be before the relationship was recorded (%s).',
                        $relationship->start_date->format('d M Y')
                    )
                );
            }
        });
    }
}