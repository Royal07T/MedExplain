<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allergy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'organization_id',
        'allergen_type',
        'allergen_name',
        'reaction_description',
        'severity',
        'status',
        'onset_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'onset_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSevere($query)
    {
        return $query->whereIn('severity', ['severe', 'life_threatening']);
    }

    public function scopeDrugAllergies($query)
    {
        return $query->where('allergen_type', 'drug');
    }

    public function scopeFoodAllergies($query)
    {
        return $query->where('allergen_type', 'food');
    }

    public function scopeEnvironmentalAllergies($query)
    {
        return $query->where('allergen_type', 'environmental');
    }
}
