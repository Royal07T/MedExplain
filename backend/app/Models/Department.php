<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'name',
    'code',
    'description',
    'capacity',
])]
class Department extends Model
{
    protected $casts = [
        'capacity' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo('App\Models\Organization');
    }

    public function clinicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department');
    }

    public function nurses(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department');
    }
}