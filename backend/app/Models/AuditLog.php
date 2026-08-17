<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'ip_address',
    'user_agent',
    'metadata',
])]
class AuditLog extends Model
{
    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditEvent::class,
            'metadata' => 'array',
        ];
    }
}