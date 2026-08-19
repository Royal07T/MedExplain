<?php

namespace App\Enums;

/**
 * Scopes that partners may request and that patients may consent to.
 */
enum PartnerScope: string
{
    case HealthRecordRead = 'health_record:read';

    public const ALL = [
        self::HealthRecordRead,
    ];
}