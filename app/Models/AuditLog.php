<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One append-only security event (Phase 3D).
 *
 * APPEND-ORIENTED BY CONSTRUCTION
 *
 * An audit trail is only evidence if it cannot be revised after the fact, so
 * every mutation path except "insert" is refused:
 *
 *   $auditLog->delete()      throws LogicException
 *   $auditLog->forceDelete() throws LogicException
 *   $auditLog->update([...]) throws LogicException
 *   AuditLog::query()->update([...]) throws LogicException
 *   AuditLog::query()->delete()       throws LogicException
 *
 * The builder-level guard matters because a mass `AuditLog::query()->delete()`
 * would otherwise bypass the model guard entirely. The remaining unguarded path
 * is raw SQL through the DB facade, which requires deliberately writing SQL to
 * destroy evidence — that is the intended bar for an application that offers no
 * audit-deletion UI, route or command at all.
 *
 * Rows are therefore only ever created, by App\Support\Audit\Auditor.
 *
 * Retention: ORWeMS does not prune audit rows yet. When retention is
 * introduced it must be an explicit, offline, reviewed operation — never a
 * model event, never a request-scoped side effect.
 *
 * @mixin Builder<AuditLog>
 */
class AuditLog extends Model
{
    /** Audit rows are written once and never revised. */
    public const UPDATED_AT = null;

    /**
     * Deliberately permissive: Auditor is the only writer, and the column set
     * itself is narrow. Guarding `$fillable` here would be security theatre
     * layered on top of a column list that already cannot carry credentials —
     * see App\Support\Audit\Auditor for the sanitisation that actually matters.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'request_id',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Append-only enforcement
     ------------------------------------------------------------------ */

    /** @throws \LogicException always — audit rows are never updated. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Audit log entries are append-only and cannot be updated.');
    }

    /** @throws \LogicException always — audit rows are never deleted. */
    public function delete()
    {
        throw new \LogicException('Audit log entries are append-only and cannot be deleted.');
    }

    /** @throws \LogicException always — audit rows are never deleted. */
    public function forceDelete()
    {
        throw new \LogicException('Audit log entries are append-only and cannot be deleted.');
    }

    /** @throws \LogicException always — audit rows are never updated. */
    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Audit log entries are append-only and cannot be deleted.');
    }

    /**
     * A query builder that refuses bulk updates and deletes, so the model-level
     * guards cannot be sidestepped with `AuditLog::query()->delete()`.
     *
     * @return Builder<AuditLog>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new AuditLogBuilder($query);
    }
}
