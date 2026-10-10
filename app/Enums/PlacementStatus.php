<?php

namespace App\Enums;

/**
 * The lifecycle state of a care placement (Phase 5).
 *
 * Derived, not free: a placement is Active exactly while it carries no end
 * date, and Closed the moment one is set. Placement::booted() keeps the two
 * in step and the MySQL status CHECK constraint refuses any row that
 * disagrees, so `status` can be read and displayed without recomputing but
 * can never drift from the dates.
 *
 * There is deliberately no third state. "Ended but not yet reviewed" or
 * "draft" would create records a caseworker must remember to finish; care
 * history is either open (the child is there now) or closed (they were, and
 * when).
 */
enum PlacementStatus: string
{
    case Active = 'active';

    case Closed = 'closed';
}