<?php

namespace App\Http\Requests;

use App\Enums\DateAccuracy;
use App\Enums\Sex;
use App\Models\Beneficiary;
use App\Support\Authorization\ScopeAuthorization;
use App\Support\Geography\GeographicHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation for beneficiary registration and amendment (Phase 4).
 *
 * ONE class serves both store and update: the rules are identical, and the
 * only difference (the duplicate check ignoring the record being edited) is
 * handled by reading the route-bound beneficiary. Two classes would drift.
 *
 * NEVER TRUSTED FROM THE BROWSER: beneficiary_ref (server-generated),
 * user_id/created_by/verified_by (the server determines the actor), status on
 * store (always starts Registered; verification is a separate action).
 */
class StoreBeneficiaryRequest extends FormRequest
{
    /**
     * The route middleware already refused guests, disabled accounts and
     * expired passwords. Returning true here is correct: *authorization* —
     * whether this operator may touch THIS record — is decided per-record by
     * the BeneficiaryPolicy in the controller, which has the record and can
     * answer precisely.
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
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'date_of_birth_accuracy' => ['nullable', Rule::enum(DateAccuracy::class)],
            'nationality_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'local_government_area_id' => ['nullable', 'integer', 'exists:local_government_areas,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'household_id' => ['nullable', 'integer', 'exists:households,id'],
            'person_id' => ['nullable', 'integer', 'exists:persons,id'],
            'programme_id' => ['required', 'integer', 'exists:programmes,id'],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Cross-field checks after the field-shape rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                // Field-shape failures already reported; cross-field checks
                // would read invalid ids and produce misleading errors.
                return;
            }

            $data = $validator->validated();

            $this->assertHierarchy($data);
            $this->assertPersonHouseholdAgreement($data);
            $this->assertOperatorScope($data, $validator);
            $this->assertNotDuplicate($data, $validator);
        });
    }

    /**
     * The submitted geography must describe one branch of the hierarchy.
     */
    private function assertHierarchy(array $data): void
    {
        GeographicHierarchy::assertAddressIsWithinHierarchy(
            isset($data['country_id']) ? (int) $data['country_id'] : null,
            isset($data['state_id']) ? (int) $data['state_id'] : null,
            isset($data['local_government_area_id']) ? (int) $data['local_government_area_id'] : null,
            isset($data['ward_id']) ? (int) $data['ward_id'] : null,
            isset($data['community_id']) ? (int) $data['community_id'] : null,
        );
    }

    /**
     * A linked roster row and a household must agree where both name one.
     */
    private function assertPersonHouseholdAgreement(array $data): void
    {
        if (empty($data['person_id']) || empty($data['household_id'])) {
            return;
        }

        $rosterHouseholdId = \App\Models\Person::query()
            ->whereKey($data['person_id'])
            ->value('household_id');

        if ($rosterHouseholdId !== null && (int) $rosterHouseholdId !== (int) $data['household_id']) {
            throw ValidationException::withMessages([
                'person_id' => 'The household roster record for this child belongs to a different household.',
            ]);
        }
    }

    /**
     * The operator must hold scope for the submitted programme + geography.
     *
     * Checked here rather than only in the controller so the denial carries
     * field errors (programme_id / geography) instead of a bare 403: the
     * operator learns WHICH dimension they lack, which is what an
     * administrator needs to fix it.
     */
    private function assertOperatorScope(array $data, $validator): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $programme = \App\Models\Programme::query()->find($data['programme_id']);

        if ($programme === null || ! ScopeAuthorization::allowsProgramme($user, $programme)) {
            $validator->errors()->add(
                'programme_id',
                'You are not scoped to this programme.'
            );
        }

        $geography = $this->submittedGeography($data);

        if ($geography === null || ! ScopeAuthorization::allowsGeography($user, $geography)) {
            $validator->errors()->add(
                'state_id',
                'You are not scoped to this geographic area.'
            );
        }
    }

    /**
     * The deepest submitted geography level, mirroring Beneficiary::geography().
     */
    private function submittedGeography(array $data): ?\Illuminate\Database\Eloquent\Model
    {
        foreach ([
            'community_id' => \App\Models\Community::class,
            'ward_id' => \App\Models\Ward::class,
            'local_government_area_id' => \App\Models\LocalGovernmentArea::class,
            'state_id' => \App\Models\State::class,
            'country_id' => \App\Models\Country::class,
        ] as $field => $model) {
            if (! empty($data[$field])) {
                return $model::query()->find($data[$field]);
            }
        }

        return null;
    }

    /**
     * Block a likely-duplicate registration.
     *
     * Matching key: same names + sex + date of birth + household. Names alone
     * are never enough to refuse (common names repeat); sex and DOB narrow
     * the key, and the household anchors it to a place. A null DOB or a null
     * household weakens the key, so those match only null-to-null rather than
     * matching everything — orphans without households must not all be
     * "duplicates" of each other.
     *
     * On update the record being edited is excluded, so saving without
     * changes never flags itself.
     */
    private function assertNotDuplicate(array $data, $validator): void
    {
        /** @var Beneficiary|null $editing */
        $editing = $this->route('beneficiary');

        $query = Beneficiary::query()
            ->where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->where('sex', $data['sex']);

        if (! empty($data['date_of_birth'])) {
            $query->whereDate('date_of_birth', $data['date_of_birth']);
        } else {
            $query->whereNull('date_of_birth');
        }

        if (! empty($data['household_id'])) {
            $query->where('household_id', $data['household_id']);
        } else {
            $query->whereNull('household_id');
        }

        if ($editing instanceof Beneficiary) {
            $query->whereKeyNot($editing->getKey());
        }

        $existing = $query->first(['id', 'beneficiary_ref']);

        if ($existing !== null) {
            $validator->errors()->add(
                'first_name',
                sprintf(
                    'A beneficiary with this name, sex, date of birth and household already exists (%s). Link to the existing record instead of registering again.',
                    $existing->beneficiary_ref
                )
            );
        }
    }
}
