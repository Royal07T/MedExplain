<?php

namespace App\Models;

use App\Enums\AmbulanceDispatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'emergency_visit_id',
    'patient_id',
    'status',
    'pickup_location',
    'destination_hospital',
    'vehicle_id',
    'dispatched_at',
    'en_route_at',
    'on_scene_at',
    'transporting_at',
    'delivered_at',
])]
class AmbulanceDispatch extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AmbulanceDispatchStatus::class,
            'dispatched_at' => 'datetime',
            'en_route_at' => 'datetime',
            'on_scene_at' => 'datetime',
            'transporting_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function emergencyVisit(): BelongsTo
    {
        return $this->belongsTo(EmergencyVisit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
