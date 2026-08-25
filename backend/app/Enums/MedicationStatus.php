<?php

namespace App\Enums;

enum MedicationStatus: string
{
    case Prescribed = 'prescribed';
    case Approved = 'approved';
    case Dispensed = 'dispensed';
    case Active = 'active';
    case Discontinued = 'discontinued';
}