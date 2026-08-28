<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'medication_id',
        'batch_number',
        'expiry_date',
        'quantity_on_hand',
        'minimum_stock_level',
        'maximum_stock_level',
        'location',
        'supplier',
        'unit_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity_on_hand' => 'integer',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'minimum_stock_level');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->minimum_stock_level;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date < now();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date <= now()->addDays($days) && $this->expiry_date > now();
    }
}
