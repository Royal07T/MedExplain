<?php

namespace App\Enums;

enum MedicationAdminStatus: string
{
    case Given = 'given';
    case Refused = 'refused';
    case Held = 'held';
    case NotGiven = 'not_given';
}
