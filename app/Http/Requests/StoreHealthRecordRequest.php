<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a NEW casework-level health record for a beneficiary (Phase 6B).
 *
 * Field-shape rules first; then the cross-field checks that carry the real
 * authorization weight for this workflow:
 *
 *  - the health concern must exist AND be active (withdrawn concerns keep
 *    their history but can no longer be chosen);
 *  - follow_up_status is one of the three coarse lifecycle values — nothing
 *    clinical, just "is there anything outstanding";
 *  - referral_to is allowed only when a referral is actually being tracked
 *    (pending or done): a destination with "no follow-up needed" is
 *    contradictory data, and contradiction here means the record cannot be
 *    trusted;
 *  - the beneficiary must have NO OPEN record for the same concern — the same
 *    child tracked twice for the same issue is a second place the truth can
 *    diverge. DIFFERENT concerns may both be open; the database unique index
 *    over the generated open_flag remains the hard stop.
 *
 * There is deliberately NO household/caregiver scope check here, unlike Phase
 * 5's placement requests: a health record references only the beneficiary
 * (whose scope the POLICY already enforces) plus a global reference row, not
 * any scoped record the form could smuggle in. Deny-by-default scope on the
 * beneficiary itself is therefore the whole of the WHERE question — exactly
 * like Phase 6A's StoreEducationRequest.
 *
 * The controller authorizes `create` on the policy (permission AND
 * beneficiary scope); this request adds the INPUT-level duplicate check, so a
 * denial names the offending field instead of being a bare 403.
 */
class StoreHealthRecordRequest extends FormRequest
{
    /**
     * Authorization runs in the controller through HealthRecordPolicy,
     * exactly like Phase 6A's StoreEducationRequest; this request owns
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
                // Field-shape failures already reported; cross-field checks
                // would read invalid ids and produce misleading errors.
                return;
            }

            $this->assertReferralCoherence($validator);
            $this->assertNoOpenDuplicate($validator);
        });
    }

    /**
     * A referral destination requires a referral being tracked. "No follow-up
     * needed" plus a destination is contradictory data.
     */
    private function assertReferralCoherence($validator): void
    {
        $data = $this->validated();

        if (($data['follow_up_status'] ?? 'none') === 'none'
            && ! empty($data['referral_to'])) {
            $validator->errors()->add(
                'referral_to',
                'A referral destination requires follow-up to be pending or done — "no follow-up needed" with a destination is contradictory.'
            );
        }
    }

    /**
     * One child, one OPEN record per concern — enforced here with a readable
     * message before the database constraint has to say it with a constraint
     * violation. Different concerns may both be open.
     */
    private function assertNoOpenDuplicate($validator): void
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        $data = $this->validated();

        $duplicate = $beneficiary->healthRecords()
            ->where('health_concern_id', $data['health_concern_id'])
            ->whereNull('end_date')
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'health_concern_id',
                'This beneficiary already has an open record for this concern. Close it first, or record the new information on the existing record.'
            );
        }
    }
}
