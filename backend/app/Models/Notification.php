<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification shown to a user (e.g. document uploaded, analysis
 * ready, plan changed, consent granted).
 */
#[Fillable(['user_id', 'title', 'body', 'type', 'data', 'read_at', 'created_at'])]
final class Notification extends Model
{
    /**
     * The user the notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Whether this notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}