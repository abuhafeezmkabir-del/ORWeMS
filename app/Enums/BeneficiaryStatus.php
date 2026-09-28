<?php

namespace App\Enums;

enum BeneficiaryStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Exited = 'exited';
    case Deceased = 'deceased';
    case Transferred = 'transferred';
}
