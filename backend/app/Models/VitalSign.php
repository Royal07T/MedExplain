<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'organization_id',
        'temperature',
        'temperature_unit',
        'heart_rate',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'respiratory_rate',
        'oxygen_saturation',
        'weight',
        'weight_unit',
        'height',
        'height_unit',
        'bmi',
        'pain_score',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    public function scopeForEncounter($query, $encounterId)
    {
        return $query->where('encounter_id', $encounterId);
    }

    public function getBloodPressureAttribute()
    {
        if ($this->blood_pressure_systolic && $this->blood_pressure_diastolic) {
            return "{$this->blood_pressure_systolic}/{$this->blood_pressure_diastolic}";
        }
        return null;
    }

    public function calculateBMI()
    {
        if ($this->weight && $this->height && $this->height_unit === 'cm') {
            $heightInMeters = $this->height / 100;
            $this->bmi = round($this->weight / ($heightInMeters * $heightInMeters), 2);
            $this->save();
        }
        return $this->bmi;
    }
}
