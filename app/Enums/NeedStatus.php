<?php

namespace App\Enums;

enum NeedStatus: string
{
    case Identified = 'identified';
    case Prioritised = 'prioritised';
    case Addressed = 'addressed';
    case Referred = 'referred';
    case Closed = 'closed';
}
