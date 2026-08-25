<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'name',
    'sku',
    'item_type',
    'status',
    'quantity_on_hand',
    'minimum_stock_level',
    'maximum_stock_level',
    'batch_number',
    'expiration_date',
    'supplier',
])]
class InventoryItem extends Model
{
    protected $casts = [
        'quantity_on_hand' => 'integer',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
        'expiration_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo('App\Models\Organization');
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->minimum_stock_level;
    }

    public function isOverStock(): bool
    {
        return $this->quantity_on_hand >= $this->maximum_stock_level;
    }

    public function needsReorder(): bool
    {
        return $this->quantity_on_hand < $this->minimum_stock_level;
    }
}