<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tech_stack' => 'array',
        'registered_at' => 'datetime',
        'approved_at' => 'datetime',
        'key_rotated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'detected_at' => 'datetime',
    ];

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}