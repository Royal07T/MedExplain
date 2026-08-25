<?php

namespace App\Enums;

enum LabOrderStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}