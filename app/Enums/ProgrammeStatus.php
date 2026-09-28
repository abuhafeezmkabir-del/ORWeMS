<?php

namespace App\Enums;

enum ProgrammeStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
