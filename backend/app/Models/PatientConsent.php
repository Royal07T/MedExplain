<?php

namespace App\Models;

use App\Enums\PartnerScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'partner_id',
    'patient_user_id',
    'scopes',
    'granted_at',
    'revoked_at',
])]
class PatientConsent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * The partner the consent was granted to.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(ApiPartner::class);
    }

    /**
     * The patient who granted the consent.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    /**
     * Whether the consent is currently active and covers the given scope.
     */
    public function isActiveFor(PartnerScope|string $scope): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return in_array($scope instanceof PartnerScope ? $scope->value : $scope, $this->scopes ?? [], true);
    }
}