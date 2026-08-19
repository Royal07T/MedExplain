<?php

namespace App\Models;

use App\Enums\PartnerScope;
use Database\Factories\ApiPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'client_id',
    'client_secret',
    'scopes',
    'quota_per_minute',
    'is_active',
])]
class ApiPartner extends Model
{
    /** @use HasFactory<ApiPartnerFactory> */
    use HasApiTokens, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'quota_per_minute' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The consents granted to this partner.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    /**
     * Whether the partner is allowed to request this scope.
     */
    public function hasScope(PartnerScope|string $scope): bool
    {
        return in_array($scope instanceof PartnerScope ? $scope->value : $scope, $this->scopes ?? [], true);
    }
}