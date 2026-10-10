<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VerifiesCareScope;
use App\Models\Beneficiary;
use App\Models\Placement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Change Placement transaction (Phase 5): close the current placement
 * and open the next one, in one request.
 *
 * This is the ONLY sanctioned way to end one placement and begin another.
 * Running close() then store() as two separate requests would leave a
 * window — literal, if the requests are seconds apart, and logical, if the
 * second one fails — where a child has NO active placement, which is the
 * state the single-active rule exists to prevent from being mistaken for
 * reality. One request, one transaction: both halves commit or neither
 * does.
 *
 * The payload therefore carries both sides:
 *
 *   current_end_date, current_closure_reason   the closing half
 *   care_arrangement_type_id, start_date, ...  the opening half (same rules
 *                                              as StorePlacementRequest)
 *
 * and the scope checks apply to the OPENING half exactly as they would on
 * the store route — passing the active placement's checks must not be a
 * way to smuggle an out-of-scope household into the next placement.
 */
class ChangePlacementRequest extends FormRequest
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
            // Closing half. start-date comparison runs in withValidator()
            // against the CURRENT placement, which is not in the payload.
            'current_end_date' => ['required', 'date', 'before_or_equal:today'],
            'current_closure_reason' => ['nullable', 'string', 'max:255'],

            // Opening half — identical to StorePlacementRequest.
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
                return;
            }

            $data = $validator->validated();

            // The change route is beneficiary-anchored (…/placements/change)
            // and carries no {placement} parameter, so the current placement
            // is resolved from the beneficiary's ACTIVE placement — the same
            // row storeChange() will close — rather than from the route.
            $current = $this->currentPlacement();

            if ($current instanceof Placement && $current->start_date !== null) {
                $currentEnd = \Illuminate\Support\Carbon::parse($data['current_end_date']);

                if ($currentEnd->lt($current->start_date)) {
                    $validator->errors()->add(
                        'current_end_date',
                        sprintf(
                            'The end date cannot be before the current placement started (%s).',
                            $current->start_date->format('d M Y')
                        )
                    );
                }

                $nextStart = \Illuminate\Support\Carbon::parse($data['start_date']);

                if ($nextStart->lt($currentEnd)) {
                    $validator->errors()->add(
                        'start_date',
                        'The new placement cannot start before the current one ends.'
                    );
                }
            }

            $this->assertHouseholdInScope($data, $validator);
            $this->assertPersonInScope($data['caregiver_person_id'] ?? null, 'caregiver_person_id', $validator);
        });
    }

    /**
     * The beneficiary's active placement, resolved from the route beneficiary
     * (the only placement the change transaction can close). Mirrors the
     * controller's own resolution so validation and commit agree on the row.
     */
    private function currentPlacement(): ?Placement
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return null;
        }

        return $beneficiary->placements()->whereNull('end_date')->first();
    }
}