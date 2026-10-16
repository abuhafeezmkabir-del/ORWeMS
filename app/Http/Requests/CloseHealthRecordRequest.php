<?php

namespace App\Http\Requests;

use App\Models\HealthRecord;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing a health record (Phase 6B).
 *
 * Closing is the one-way step in the workflow: it sets the end date and the
 * row stays forever as history. Two rules carry the meaning of that step:
 *
 *  - the end date cannot be before the record date (a record cannot have
 *    ended before it began) — checked against THIS record's date, which is a
 *    different field from anything in the payload, hence the after() hook
 *    rather than an `after_or_equal` rule;
 *  - the end date cannot be in the future: "closed" means tracking has ended
 *    now. A future closure is recorded when it happens — closing ahead would
 *    put the child's present health tracking out of date for the interval.
 *
 * closure_reason is optional (not every close has a story beyond the
 * routine), but when present it is stored on the row and the audit event
 * records who closed and when either way.
 */
class CloseHealthRecordRequest extends FormRequest
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

            /** @var HealthRecord|null $record */
            $record = $this->route('healthRecord');

            if (! $record instanceof HealthRecord || $record->record_date === null) {
                return;
            }

            $end = \Illuminate\Support\Carbon::parse($this->validated()['end_date']);

            if ($end->lt($record->record_date)) {
                $validator->errors()->add(
                    'end_date',
                    sprintf(
                        'The end date cannot be before the record was opened (%s).',
                        $record->record_date->format('d M Y')
                    )
                );
            }
        });
    }
}
