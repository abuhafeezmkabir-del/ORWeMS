<?php

namespace App\Http\Requests;

use App\Enums\Sex;
use App\Http\Requests\Concerns\VerifiesCareScope;
use App\Models\Beneficiary;
use App\Models\PersonRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a family/care relationship for a beneficiary (Phase 5).
 *
 * The edge always points at EXACTLY ONE related party, chosen explicitly via
 * related_party_type — the shape person_relationships has enforced in the
 * database since Phase 2.1 (its CHECK refuses both-sides and neither-side
 * rows), and PersonRelationship::saving repeats as a model guard. This
 * request states the same rule as validation, so a wrong combination is a
 * field error rather than a driver error.
 *
 * Three counterpart modes, each with its own extra weight:
 *
 *   existing_person      the person must live in a household in the
 *                        operator's area, and must not be the child's own
 *                        roster row (a child is not related to themselves);
 *   new_person           creates the roster person in the same request;
 *                        persons.create is required IN ADDITION to
 *                        relationships.create, checked in authorize() —
 *                        recording a relationship must not be a back door
 *                        to growing household rosters without the roster
 *                        permission;
 *   existing_beneficiary the counterpart child must be a different,
 *                        non-archived, in-scope beneficiary (typically a
 *                        sibling already registered separately).
 *
 * Duplicate open edges are refused: the same kind of relationship to the
 * same person, not yet ended, already exists and should be amended or ended
 * rather than shadowed by a second row.
 */
class StoreRelationshipRequest extends FormRequest
{
    use VerifiesCareScope;

    /**
     * Creating a relationship to a person who does not exist yet ALSO
     * creates the person — so it additionally requires the roster
     * permission. A 403 (not a validation error) is the right answer for a
     * missing capability: it is not about this input.
     */
    public function authorize(): bool
    {
        if ($this->input('related_party_type') === 'new_person') {
            return $this->user()?->can('persons.create') ?? false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relationship_type_id' => [
                'required', 'integer',
                Rule::exists('relationship_types', 'id')->where('is_active', true),
            ],
            'related_party_type' => [
                'required', 'string', Rule::in(['existing_person', 'new_person', 'existing_beneficiary']),
            ],
            'related_person_id' => [
                'nullable', 'integer',
                Rule::exists('persons', 'id')->whereNull('deleted_at'),
            ],
            'related_beneficiary_id' => [
                'nullable', 'integer',
                Rule::exists('beneficiaries', 'id')->whereNull('deleted_at'),
            ],
            // New-person fields, required only in that mode (checked below).
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'household_id' => [
                'nullable', 'integer',
                Rule::exists('households', 'id')->whereNull('deleted_at'),
            ],
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

            $this->assertExactlyOneRelatedParty($data, $validator);
            $this->assertNotSelf($data, $validator);
            $this->assertCounterpartsInScope($data, $validator);
            $this->assertNoDuplicateOpenEdge($data, $validator);
        });
    }

    /**
     * The chosen mode must agree with the submitted ids — and new_person
     * must carry the fields its person row needs to exist.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertExactlyOneRelatedParty(array $data, $validator): void
    {
        $personId = ! empty($data['related_person_id']);
        $beneficiaryId = ! empty($data['related_beneficiary_id']);

        switch ($data['related_party_type']) {
            case 'existing_person':
                if (! $personId || $beneficiaryId) {
                    $validator->errors()->add('related_person_id', 'Choose the person this relationship points to.');
                }
                break;

            case 'existing_beneficiary':
                if (! $beneficiaryId || $personId) {
                    $validator->errors()->add('related_beneficiary_id', 'Choose the beneficiary this relationship points to.');
                }
                break;

            case 'new_person':
                if ($personId || $beneficiaryId) {
                    $validator->errors()->add('related_party_type', 'A new person cannot be combined with an existing counterpart — clear the other selection.');
                }
                if (empty($data['first_name']) || empty($data['last_name'])) {
                    $validator->errors()->add('first_name', 'A new person needs at least a first name and a last name.');
                }
                break;
        }
    }

    /**
     * No self-edges: a child is not related to themselves, and the child's
     * own roster row is not a counterpart.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNotSelf(array $data, $validator): void
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        if (! empty($data['related_person_id'])
            && $beneficiary->person_id !== null
            && (int) $data['related_person_id'] === (int) $beneficiary->person_id) {
            $validator->errors()->add(
                'related_person_id',
                'That person is this beneficiary’s own roster record — it cannot also be their relative.'
            );
        }

        if (! empty($data['related_beneficiary_id'])
            && (int) $data['related_beneficiary_id'] === (int) $beneficiary->getKey()) {
            $validator->errors()->add(
                'related_beneficiary_id',
                'A beneficiary cannot be related to themselves.'
            );
        }
    }

    /**
     * Every counterpart must be verifiable against the operator's scope.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertCounterpartsInScope(array $data, $validator): void
    {
        if ($data['related_party_type'] === 'existing_person') {
            $this->assertPersonInScope($data['related_person_id'] ?? null, 'related_person_id', $validator);
        }

        if ($data['related_party_type'] === 'existing_beneficiary' && ! empty($data['related_beneficiary_id'])) {
            $related = \App\Models\Beneficiary::query()->find($data['related_beneficiary_id']);

            if ($related === null
                || ! \App\Support\Authorization\ScopeAuthorization::allowsBeneficiary($this->user(), $related)) {
                $validator->errors()->add(
                    'related_beneficiary_id',
                    'You are not scoped to that beneficiary’s record.'
                );
            }
        }

        if ($data['related_party_type'] === 'new_person') {
            // No household named → the person joins the child's own household,
            // whose area the operator has already been scoped to for this
            // beneficiary. A NAMED household is checked on its own terms.
            $this->assertHouseholdInScope($data, $validator);
        }
    }

    /**
     * One open edge of a given kind per pair — the "is this already
     * recorded?" check, refusing a shadow row rather than letting two rows
     * disagree about the same family fact.
     *
     * Edge shape (Phase 2): person_id is the PERSON side, and exactly one of
     * related_person_id / related_beneficiary_id is the other side. The
     * workflows this phase builds always anchor related_beneficiary_id to
     * the child on whose case file the form lives, so:
     *
     *   existing_person      person_id = the chosen relative
     *   existing_beneficiary person_id = the subject child's roster row
     *                        (which the controller creates first if absent)
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoDuplicateOpenEdge(array $data, $validator): void
    {
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        if ($data['related_party_type'] === 'existing_person') {
            $personId = (int) $data['related_person_id'];
        } elseif ($data['related_party_type'] === 'existing_beneficiary') {
            // Without a roster row for the subject there is no edge yet, so
            // there can be no duplicate of this shape either.
            if ($beneficiary->person_id === null) {
                return;
            }
            $personId = (int) $beneficiary->person_id;
        } else {
            // new_person: the person row does not exist yet — nothing to
            // duplicate against.
            return;
        }

        $duplicate = PersonRelationship::query()
            ->where('relationship_type_id', $data['relationship_type_id'])
            ->where('person_id', $personId)
            ->where('related_beneficiary_id', $beneficiary->getKey())
            ->whereNull('end_date')
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'relationship_type_id',
                'This relationship is already recorded and has not been ended. End the existing link before recording it again.'
            );
        }
    }
}