<?php

namespace App\Enums;

enum AcuityLevel: string
{
    case Resuscitation = 'resuscitation';
    case Emergent = 'emergent';
    case Urgent = 'urgent';
    case Nonurgent = 'non-urgent';
}
