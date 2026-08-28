<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProblemList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'organization_id',
        'icd10_code',
        'icd10_description',
        'clinical_notes',
        'status',
        'onset_date',
        'resolved_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'onset_date' => 'date',
        'resolved_date' => 'date',
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

    public function scopeChronic($query)
    {
        return $query->where('status', 'chronic');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}
