<?php

namespace App\Http\Controllers;

use App\Enums\PlacementStatus;
use App\Enums\SecurityEvent;
use App\Http\Requests\ChangePlacementRequest;
use App\Http\Requests\ClosePlacementRequest;
use App\Http\Requests\StorePlacementRequest;
use App\Http\Requests\UpdatePlacementRequest;
use App\Models\Beneficiary;
use App\Models\CareArrangementType;
use App\Models\Household;
use App\Models\Person;
use App\Models\Placement;
use App\Support\Audit\Auditor;
use App\Support\Authorization\ScopeAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Care placement management (Phase 5).
 *
 * The same authorization chain as BeneficiaryController, link by link:
 * route middleware (permission) → policy (permission AND beneficiary scope)
 * → form request (input-level scope for household/caregiver) → model
 * guards + database constraints. What is different is the lifecycle:
 * placements are created, amended while active, and CLOSED — and there is
 * deliberately no destroy. A child's care history is the evidence ORWeMS
 * exists to keep, so the terminal operation is closure, never deletion.
 *
 * storeChange is the one compound action: closing the current placement and
 * opening the next happen in ONE transaction, because a child is somewhere
 * at every moment, and a workflow that could half-commit would record the
 * opposite.
 */
class PlacementController extends Controller
{
    /**
     * The full placement history for one child, newest first.
     */
    public function index(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('viewFor', [Placement::class, $beneficiary]);

        // The relation bakes an ASC start_date order for history rendering;
        // the listing itself wants newest first, so reset before re-sorting.
        $placements = $beneficiary->placements()
            ->with([
                'careArrangementType:id,code,name',
                'household:id,household_code,household_name',
                'caregiverPerson:id,first_name,last_name',
            ])
            ->reorder()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return view('placements.index', [
            'beneficiary' => $beneficiary,
            'placements' => $placements,
            'currentPlacement' => $placements->first(fn (Placement $p): bool => $p->isActive()),
        ]);
    }

    /**
     * The new-placement form.
     */
    public function create(Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('create', [Placement::class, $beneficiary]);

        return view('placements.create', [
            'beneficiary' => $beneficiary,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Record a new placement. Refuses (with a field error, via the form
     * request) when the child already has an active one.
     */
    public function store(StorePlacementRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('create', [Placement::class, $beneficiary]);

        $placement = $beneficiary->placements()->create($request->validated());

        Auditor::record(
            SecurityEvent::CarePlacementCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'placement_id' => $placement->getKey(),
                'arrangement' => $placement->careArrangementType?->code,
                'start_date' => $placement->start_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Care placement recorded.');
    }

    /**
     * The change form: close the current arrangement and open the next,
     * shown together so the operator sees both halves they are committing.
     */
    public function change(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);

        $current = $this->activePlacement($beneficiary);

        if ($current === null) {
            abort(404);
        }

        // Changing is closing AND creating: both capabilities, or neither.
        $this->authorize('close', $current);
        $this->authorize('create', [Placement::class, $beneficiary]);

        return view('placements.change', [
            'beneficiary' => $beneficiary,
            'currentPlacement' => $current,
            ...$this->formLookups(),
        ]);
    }

    /**
     * The change transaction: old placement closed, new one opened, one
     * commit, two audit events.
     */
    public function storeChange(ChangePlacementRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $current = $this->activePlacement($beneficiary);

        if ($current === null) {
            abort(404);
        }

        $this->authorize('close', $current);
        $this->authorize('create', [Placement::class, $beneficiary]);

        $data = $request->validated();

        DB::transaction(function () use ($current, $beneficiary, $data): void {
            $current->end_date = Carbon::parse($data['current_end_date']);
            $current->closure_reason = $data['current_closure_reason'] ?? null;
            $current->status = PlacementStatus::Closed;
            $current->save();

            $beneficiary->placements()->create([
                'care_arrangement_type_id' => $data['care_arrangement_type_id'],
                'household_id' => $data['household_id'] ?? null,
                'caregiver_person_id' => $data['caregiver_person_id'] ?? null,
                'start_date' => $data['start_date'],
                'placement_reason' => $data['placement_reason'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        Auditor::record(
            SecurityEvent::CarePlacementClosed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'placement_id' => $current->getKey(),
                'end_date' => $current->end_date?->toDateString(),
                'via' => 'change',
            ],
        );
        Auditor::record(
            SecurityEvent::CarePlacementCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'via' => 'change',
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Placement changed: the previous arrangement is closed and the new one recorded.');
    }

    /**
     * The amendment form for an ACTIVE placement.
     */
    public function edit(Beneficiary $beneficiary, Placement $placement): View
    {
        $this->assertPlacementBelongsTo($beneficiary, $placement);
        $this->authorize('update', $placement);

        return view('placements.edit', [
            'beneficiary' => $beneficiary,
            'placement' => $placement,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Amend an ACTIVE placement. Closed placements are refused by the policy
     * — history does not get rewritten through an edit form.
     */
    public function update(UpdatePlacementRequest $request, Beneficiary $beneficiary, Placement $placement): RedirectResponse
    {
        $this->assertPlacementBelongsTo($beneficiary, $placement);
        $this->authorize('update', $placement);

        $placement->update($request->validated());

        Auditor::record(
            SecurityEvent::CarePlacementUpdated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'placement_id' => $placement->getKey(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Placement updated.');
    }

    /**
     * The closure confirmation. Closing is consequential and gets a
     * confirm screen + POST, exactly like archiving a beneficiary.
     */
    public function close(Beneficiary $beneficiary, Placement $placement): View
    {
        $this->assertPlacementBelongsTo($beneficiary, $placement);
        $this->authorize('close', $placement);

        return view('placements.close', [
            'beneficiary' => $beneficiary,
            'placement' => $placement,
        ]);
    }

    /**
     * Perform the closure. The row remains — closed, dated, explained — as
     * the history entry for this period of the child's life.
     */
    public function confirmClose(ClosePlacementRequest $request, Beneficiary $beneficiary, Placement $placement): RedirectResponse
    {
        $this->assertPlacementBelongsTo($beneficiary, $placement);
        $this->authorize('close', $placement);

        $data = $request->validated();

        $placement->end_date = Carbon::parse($data['end_date']);
        $placement->closure_reason = $data['closure_reason'] ?? null;
        $placement->status = PlacementStatus::Closed;
        $placement->save();

        Auditor::record(
            SecurityEvent::CarePlacementClosed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'placement_id' => $placement->getKey(),
                'end_date' => $placement->end_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Placement closed. It remains in the history.');
    }

    /**
     * A placement resolved under a beneficiary URL must BE that
     * beneficiary's — anything else is a 404, because a cross-record id in
     * a URL is a mistake (or an attempt), and answering it with 403 would
     * confirm the id exists.
     */
    private function assertPlacementBelongsTo(Beneficiary $beneficiary, Placement $placement): void
    {
        abort_unless(
            $beneficiary->exists
            && (int) $placement->beneficiary_id === (int) $beneficiary->getKey(),
            404
        );
    }

    private function activePlacement(Beneficiary $beneficiary): ?Placement
    {
        return $beneficiary->placements()
            ->whereNull('end_date')
            ->with([
                'careArrangementType:id,code,name',
                'household:id,household_code,household_name',
                'caregiverPerson:id,first_name,last_name',
            ])
            ->first();
    }

    /**
     * Lookups for the placement forms: only ACTIVE arrangement kinds, and
     * households/persons whose AREA is inside this operator's geographic
     * scope — the same expansion the beneficiary forms use, so a dropdown
     * never offers a choice the submit check would then refuse. The
     * submit-time check stays regardless; a form is not a control.
     *
     * Households without a community and persons without a household are
     * excluded: they carry no location to verify scope against, and scope
     * is deny-by-default.
     *
     * @return array<string, mixed>
     */
    private function formLookups(): array
    {
        $user = request()->user();
        $expanded = $user !== null ? ScopeAuthorization::expandedScopeIdsForForm($user) : [];
        $communityIds = $expanded['communities'] ?? [0];

        return [
            'arrangementTypes' => CareArrangementType::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description']),
            'households' => Household::query()
                ->whereIn('community_id', $communityIds)
                ->orderBy('household_code')
                ->get(['id', 'household_code', 'household_name', 'community_id']),
            'persons' => Person::query()
                ->whereHas('household', fn ($query) => $query->whereIn('community_id', $communityIds))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'household_id']),
        ];
    }
}