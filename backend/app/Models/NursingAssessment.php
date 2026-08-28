<?php

namespace App\Models;

use App\Enums\AssessmentType;
use App\Enums\FallRiskLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'assessment_type',
    'template_name',
    'assessment_data',
    'findings',
    'notes',
    'assessment_time',
    'performed_by',
    'fall_risk_score',
    'fall_risk_level',
    'pressure_ulcer_stage',
])]
class NursingAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'assessment_type' => AssessmentType::class,
            'assessment_data' => 'array',
            'fall_risk_level' => FallRiskLevel::class,
            'assessment_time' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
