<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder for App\Models\AuditLog that refuses bulk mutation.
 *
 * Eloquent's `Model::delete()` runs through the model, but
 * `Model::query()->delete()` and `Model::query()->update([...])` are executed
 * directly by the builder and would never reach the model's guards. Because a
 * mass delete is exactly how an audit trail gets quietly cleared, the refusal
 * lives here as well.
 *
 * @extends Builder<AuditLog>
 */
class AuditLogBuilder extends Builder
{
    /** @throws \LogicException always — audit rows are never updated. */
    public function update(array $values)
    {
        throw new \LogicException('Audit log entries are append-only and cannot be updated.');
    }

    /** @throws \LogicException always — audit rows are never deleted. */
    public function delete()
    {
        throw new \LogicException('Audit log entries are append-only and cannot be deleted.');
    }
}
