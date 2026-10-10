<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEvent;
use App\Http\Requests\EndRelationshipRequest;
use App\Http\Requests\StoreRelationshipRequest;
use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use App\Support\Audit\Auditor;
use App\Support\Authorization\ScopeAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Family/care relationship workflow for a beneficiary's case file (Phase 5).
 *
 * Phase 2 built the person_relationships model (exactly-one-related-party
 * CHECK included) but left it with no workflow — relationships existed only
 * as data someone could insert by hand. These actions are the first
 * controlled interface over it, and every edge they write is anchored to
 * the beneficiary on whose case file the form lives:
 *
 *   existing person       person_id = the chosen relative
 *   new person            the person is created here (needs persons.create
 *                         — checked in the form request — so relationship
 *                         recording cannot become a back door to growing
 *                         household rosters)
 *   existing beneficiary  person_id = the subject child's roster row, which
 *                         is created from the child's own identity first if
 *                         the child has none yet (sibling links)
 *
 * Edges are created and ENDED, never deleted — the same lifecycle rule as
 * placements, for the same reason: a recorded family fact is evidence.
 * Ending is a separate permission (relationships.update) from recording
 * (relationships.create), so separation of duties reaches the family
 * record too.
 */
class BeneficiaryRelationshipController extends Controller
{
    /**
     * The record-a-relationship form.
     */
    public function create(Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('create', [PersonRelationship::class, $beneficiary]);

        return view('beneficiaries.relationships.create', [
            'beneficiary' => $beneficiary,
            ...$this->formLookups($beneficiary),
        ]);
    }

    /**
     * Record the relationship. The roster side is resolved here, inside one
     * transaction with the edge, so a half-created pair can never exist.
     */
    public function store(StoreRelationshipRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('create', [PersonRelationship::class, $beneficiary]);

        $data = $request->validated();

        DB::transaction(function () use ($beneficiary, $data): void {
            $personId = match ($data['related_party_type']) {
                'existing_person' => (int) $data['related_person_id'],
                'new_person' => $this->createPerson($beneficiary, $data)->getKey(),
                'existing_beneficiary' => $this->rosterPersonFor(
                    Beneficiary::query()->findOrFail($data['related_beneficiary_id'])
                )->getKey(),
            };

            PersonRelationship::create([
                'relationship_type_id' => $data['relationship_type_id'],
                'person_id' => $personId,
                'related_beneficiary_id' => $beneficiary->getKey(),
                'start_date' => $data['start_date'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        Auditor::record(
            SecurityEvent::BeneficiaryRelationshipCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'relationship_type' => RelationshipType::query()->find($data['relationship_type_id'])?->code,
                'counterpart' => $data['related_party_type'],
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Relationship recorded.');
    }

    /**
     * The end-a-relationship confirmation.
     */
    public function end(Request $request, Beneficiary $beneficiary, PersonRelationship $relationship): View
    {
        $this->assertRelationshipBelongsTo($beneficiary, $relationship);
        $this->authorize('update', $relationship);

        return view('beneficiaries.relationships.end', [
            'beneficiary' => $beneficiary,
            'relationship' => $relationship,
        ]);
    }

    /**
     * Perform the ending. The row stays, dated — the link existed, and now
     * it has stopped.
     */
    public function confirmEnd(EndRelationshipRequest $request, Beneficiary $beneficiary, PersonRelationship $relationship): RedirectResponse
    {
        $this->assertRelationshipBelongsTo($beneficiary, $relationship);
        $this->authorize('update', $relationship);

        $relationship->end_date = Carbon::parse($request->validated()['end_date']);
        $relationship->save();

        Auditor::record(
            SecurityEvent::BeneficiaryRelationshipUpdated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'relationship_id' => $relationship->getKey(),
                'action' => 'ended',
                'end_date' => $relationship->end_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Relationship ended. It remains in the record.');
    }

    /**
     * An edge resolved under a beneficiary URL must BE that beneficiary's.
     */
    private function assertRelationshipBelongsTo(Beneficiary $beneficiary, PersonRelationship $relationship): void
    {
        abort_unless(
            $beneficiary->exists
            && (int) $relationship->related_beneficiary_id === (int) $beneficiary->getKey(),
            404
        );
    }

    /**
     * Create the roster person for a new_person counterpart.
     *
     * @param  array<string, mixed>  $data
     */
    private function createPerson(Beneficiary $beneficiary, array $data): Person
    {
        return Person::create([
            // No household named → the person joins the child's household
            // (the form preselects it; the request validates any NAMED
            // household against scope).
            'household_id' => $data['household_id'] ?? $beneficiary->household_id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'sex' => $data['sex'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
        ]);
    }

    /**
     * The counterpart child's roster row — created from the child's own
     * identity when missing, so sibling edges have a person side without
     * the operator having to notice the roster gap first.
     */
    private function rosterPersonFor(Beneficiary $counterpart): Person
    {
        if ($counterpart->person !== null) {
            return $counterpart->person;
        }

        $person = Person::create([
            'household_id' => $counterpart->household_id,
            'first_name' => $counterpart->first_name,
            'last_name' => $counterpart->last_name,
            'sex' => $counterpart->sex?->value,
            'date_of_birth' => $counterpart->date_of_birth?->toDateString(),
        ]);

        $counterpart->person()->associate($person);
        $counterpart->save();

        return $person;
    }

    /**
     * Form lookups: active relationship kinds, in-scope persons, and
     * in-scope children other than this one. Scope-filtered at the source,
     * re-checked at submit — a form is not a control.
     *
     * @return array<string, mixed>
     */
    private function formLookups(Beneficiary $beneficiary): array
    {
        $user = request()->user();
        $expanded = $user !== null ? ScopeAuthorization::expandedScopeIdsForForm($user) : [];
        $communityIds = $expanded['communities'] ?? [0];

        return [
            'relationshipTypes' => RelationshipType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'persons' => Person::query()
                ->whereHas('household', fn ($query) => $query->whereIn('community_id', $communityIds))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'household_id']),
            'counterpartBeneficiaries' => Beneficiary::query()
                ->accessibleTo($user)
                ->whereKeyNot($beneficiary->getKey())
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'beneficiary_ref', 'first_name', 'last_name']),
            'households' => Household::query()
                ->whereIn('community_id', $communityIds)
                ->orderBy('household_code')
                ->get(['id', 'household_code', 'household_name']),
        ];
    }
}