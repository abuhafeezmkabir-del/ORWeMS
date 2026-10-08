<?php
namespace App\Http\Controllers;
use App\Enums\BeneficiaryStatus;
use App\Enums\SecurityEvent;
use App\Http\Requests\StoreBeneficiaryRequest;
use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use App\Support\Audit\Auditor;
use App\Support\Authorization\ScopeAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
/**
 * Beneficiary registration and case-file management (Phase 4).
 *
 * THE AUTHORIZATION CHAIN, enforced per action:
 *
 *   route middleware   auth > account.active > password.fresh > permission:*
 *   controller         $this->authorize('<ability>', $beneficiary) > policy
 *   form request       scope for the SUBMITTED programme/geography + hierarchy
 *   query layer        scopeAccessibleTo() — rows never leave the database
 *
 * The route-level `permission:*` is the coarse gate (fail fast on a missing
 * capability); the policy re-checks the permission AND the scope for the
 * specific record. Both layers stay because they answer different questions:
 * "may this operator do this kind of thing at all" vs "may they do it to
 * THIS record". Either layer alone would be insufficient.
 *
 * Route-model binding resolves by beneficiary_ref (see routes/web.php), so
 * internal ids never appear in URLs. An out-of-scope reference resolves to
 * the model and is then refused by the policy with a 403: existence is
 * disclosed by design, because a reference that 404s for one operator and
 * 403s for another is the correct, auditable behaviour.
 *
 * There is deliberately NO destroy action. ORWeMS holds historical records
 * of children; permanent destruction is not an operation. Archiving
 * (soft-delete) is the terminal action, gated by beneficiaries.archive.
 */
class BeneficiaryController extends Controller
{
    /**
     * The scoped, filterable beneficiary listing.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Beneficiary::class);

        $query = Beneficiary::query()
            ->accessibleTo($request->user())
            ->with([
                'programme:id,name,code',
                'state:id,name',
                'localGovernmentArea:id,name',
                'community:id,name',
                'ward:id,name',
            ]);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'programme_id' => ['nullable', 'integer'],
            'state_id' => ['nullable', 'integer'],
            'local_government_area_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], '', $filters['q']).'%';
            $query->where(function ($query) use ($term): void {
                $query->where('beneficiary_ref', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        foreach (['programme_id', 'state_id', 'local_government_area_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->whereDate('registration_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('registration_date', '<=', $filters['to']);
        }
        $beneficiaries = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
        return view('beneficiaries.index', [
            'beneficiaries' => $beneficiaries,
            'filters' => $filters,
        ]);
    }

    /**
     * Show a single beneficiary. Out-of-scope records resolve to the model
     * but are refused by the policy with a 403: existence is disclosed by
     * design, because a reference that 404s for one operator and 403s for
     * another is the correct, auditable behaviour.
     */
    public function show(Beneficiary $beneficiary): View
    {
        if (! $beneficiary->exists) {
            abort(404);
        }
        $this->authorize('view', $beneficiary);
        $beneficiary->load([
            'programme:id,name,code,status',
            'country:id,name',
            'state:id,name',
            'localGovernmentArea:id,name',
            'ward:id,name',
            'community:id,name',
            'household:id,household_code,household_name',
            'person:id,first_name,last_name,household_id',
        ]);
        return view('beneficiaries.show', [
            'beneficiary' => $beneficiary,
        ]);
    }
    /** The registration form. */
    public function create(): View
    {
        $this->authorize('create', Beneficiary::class);

        return view('beneficiaries.create', $this->formLookups());
    }
    /** Register a beneficiary. */
    public function store(StoreBeneficiaryRequest $request): RedirectResponse
    {
        $this->authorize('create', Beneficiary::class);
        $beneficiary = Beneficiary::create([
            ...$request->validated(),
            'status' => BeneficiaryStatus::Registered,
        ]);
        Auditor::record(
            SecurityEvent::BeneficiaryCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary,
            context: ['beneficiary_ref' => $beneficiary->beneficiary_ref],
        );
        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', sprintf(
                'Beneficiary %s registered.',
                $beneficiary->beneficiary_ref
            ));
    }
    /** The amendment form. */
    public function edit(Beneficiary $beneficiary): View
    {
        $this->authorize('update', $beneficiary);
        return view('beneficiaries.edit', [
            'beneficiary' => $beneficiary,
            ...$this->formLookups(),
        ]);
    }
    /** Amend a beneficiary record. */
    public function update(StoreBeneficiaryRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('update', $beneficiary);
        $beneficiary->update($request->validated());
        Auditor::record(
            SecurityEvent::BeneficiaryUpdated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: ['beneficiary_ref' => $beneficiary->beneficiary_ref],
        );
        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', sprintf(
                'Beneficiary %s updated.',
                $beneficiary->beneficiary_ref
            ));
    }
    /**
     * Verification is a state change that is only reachable by the scoped
     * manager. Idempotent — verifying an already-active record returns
     * success without writing a second audit row, so a double-click cannot
     * manufacture history.
     */
    public function verify(Request $request, Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('verify', $beneficiary);
        if ($beneficiary->status !== BeneficiaryStatus::Active) {
            $beneficiary->update(['status' => BeneficiaryStatus::Active]);
            Auditor::record(
                SecurityEvent::BeneficiaryVerified,
                actor: Auditor::currentActor(),
                subject: $beneficiary->fresh(),
                context: ['beneficiary_ref' => $beneficiary->beneficiary_ref],
            );
        }
        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', sprintf(
                'Beneficiary %s verified.',
                $beneficiary->beneficiary_ref
            ));
    }
    /**
     * Archive a beneficiary record.
     *
     * POST + confirm screen (archive() shows the confirmation, confirmArchive()
     * performs it): leaving active view is consequential and must not happen
     * by link, prefetch or double-submit. DELETE/confirmArchive() refuses an
     * already-archived record rather than re-auditing it.
     */
    public function archive(Beneficiary $beneficiary): View
    {
        $this->authorize('archive', $beneficiary);
        return view('beneficiaries.archive', [
            'beneficiary' => $beneficiary,
        ]);
    }
    public function confirmArchive(Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('archive', $beneficiary);
        if ($beneficiary->trashed()) {
            return redirect()
                ->route('beneficiaries.index')
                ->with('info', 'That beneficiary is already archived.');
        }
        $ref = $beneficiary->beneficiary_ref;
        $beneficiary->delete();
        Auditor::record(
            SecurityEvent::BeneficiaryArchived,
            actor: Auditor::currentActor(),
            subject: $beneficiary,
            context: ['beneficiary_ref' => $ref],
        );
        return redirect()
            ->route('beneficiaries.index')
            ->with('success', sprintf('Beneficiary %s archived.', $ref));
    }
    /** Programme and geography options for the registration/amendment forms. */
    private function formLookups(): array
    {
        $user = request()->user();
        $programmes = $user !== null
            ? $user->activeProgrammes()->orderBy('name')->get(['programmes.id', 'name', 'code'])
            : collect();
        $expanded = $user !== null
            ? ScopeAuthorization::expandedScopeIdsForForm($user)
            : null;
        return [
            'programmes' => $programmes,
            'states' => State::query()
                ->when($expanded !== null, fn ($query) => $query->whereIn('id', $expanded['states']))
                ->orderBy('name')->get(['id', 'name']),
            'localGovernmentAreas' => LocalGovernmentArea::query()
                ->when($expanded !== null, fn ($query) => $query->whereIn('id', $expanded['lgas']))
                ->orderBy('name')->get(['id', 'name', 'state_id']),
            'wards' => Ward::query()
                ->when($expanded !== null, fn ($query) => $query->whereIn('id', $expanded['wards']))
                ->orderBy('name')->get(['id', 'name', 'local_government_area_id']),
            'communities' => Community::query()
                ->when($expanded !== null, fn ($query) => $query->whereIn('id', $expanded['communities']))
                ->orderBy('name')->get(['id', 'name', 'ward_id', 'local_government_area_id']),
        ];
    }
}

