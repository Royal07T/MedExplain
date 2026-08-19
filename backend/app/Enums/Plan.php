<?php

namespace App\Enums;

/**
 * The user's subscription plan.
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }
}