<?php

namespace App\Enums;

enum AssessmentType: string
{
    case Admission = 'admission';
    case Shift = 'shift';
    case Falls = 'falls';
    case PressureUlcer = 'pressure_ulcer';
    case General = 'general';
}
