<?php

namespace App\Enums;

enum DateAccuracy: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case Unknown = 'unknown';
}
