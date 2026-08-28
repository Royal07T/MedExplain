<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTestCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'test_code',
        'test_name',
        'description',
        'category',
        'specimen_type',
        'container_type',
        'turnaround_hours',
        'cost',
        'reference_ranges',
        'critical_values',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'reference_ranges' => 'array',
        'critical_values' => 'array',
        'is_active' => 'boolean',
        'turnaround_hours' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getReferenceRangeForAge($age, $gender = null): ?array
    {
        if (!$this->reference_ranges) return null;

        foreach ($this->reference_ranges as $range) {
            $ageMatch = $age >= ($range['min_age'] ?? 0) && $age <= ($range['max_age'] ?? 999);
            $genderMatch = !$gender || !$range['gender'] || $range['gender'] === $gender;
            
            if ($ageMatch && $genderMatch) {
                return $range;
            }
        }

        return null;
    }

    public function isCriticalValue($value): bool
    {
        if (!$this->critical_values) return false;

        foreach ($this->critical_values as $critical) {
            $low = $critical['low'] ?? null;
            $high = $critical['high'] ?? null;

            if ($low !== null && $value <= $low) return true;
            if ($high !== null && $value >= $high) return true;
        }

        return false;
    }
}
