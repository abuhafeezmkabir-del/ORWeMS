<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Amending an OPEN health record (Phase 6B).
 *
 * Deliberately narrower than the store rules: NO end_date and NO
 * closure_reason fields exist here, so an amendment cannot close a record —
 * closing is health_records.close, its own permission, its own screen, its
 * own audit event. Only the record's substance (concern, summary, record
 * date, follow-up state, referral destination, notes) is amenable.
 *
 * end_date and closure_reason are also not fillable on the model, so even a
 * tampered request that smuggled the fields past validation could not mass-
 * assign them; two independent refusals, as usual.
 *
 * The concern may be CHANGED by an amendment (a record first logged as
 * general wellbeing may turn out to be a referral), but the changed concern
 * must not collide with another OPEN record for the same child — the
 * no-exact-open-duplicate rule binds amendments as well as creations.
 */
class UpdateHealthRecordRequest extends FormRequest
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
            'health_concern_id' => [
                'required', 'integer',
                Rule::exists('health_concerns', 'id')->where('is_active', true),
            ],
            'summary' => ['required', 'string', 'max:500'],
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'follow_up_status' => ['required', 'string', Rule::in(['none', 'pending', 'done'])],
            'referral_to' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $data = $this->validated();

            if (($data['follow_up_status'] ?? 'none') === 'none'
                && ! empty($data['referral_to'])) {
                $validator->errors()->add(
                    'referral_to',
                    'A referral destination requires follow-up to be pending or done — "no follow-up needed" with a destination is contradictory.'
                );
            }
        });
    }
}
