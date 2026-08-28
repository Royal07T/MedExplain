<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formulary extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'medication_id',
        'formulary_code',
        'tier',
        'requires_prior_authorization',
        'quantity_limit',
        'days_supply_limit',
        'restrictions',
        'alternatives',
        'is_active',
        'effective_date',
        'discontinued_date',
        'notes',
    ];

    protected $casts = [
        'requires_prior_authorization' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'discontinued_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('discontinued_date')
                    ->orWhere('discontinued_date', '>', now());
            });
    }

    public function scopeByTier($query, $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeRequiresAuth($query)
    {
        return $query->where('requires_prior_authorization', true);
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->discontinued_date && $this->discontinued_date <= now()) return false;
        return true;
    }
}
