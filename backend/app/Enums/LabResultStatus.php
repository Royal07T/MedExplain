<?php

namespace App\Enums;

enum LabResultStatus: string
{
    case WithinRange = 'within_range';
    case AboveRange = 'above_range';
    case BelowRange = 'below_range';
    case Positive = 'positive';
    case Negative = 'negative';
    case Unknown = 'unknown';
}