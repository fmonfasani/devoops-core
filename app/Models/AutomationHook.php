<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationHook extends Model
{
    protected $fillable = [
        'organization_id',
        'team_id',
        'user_id',
        'name',
        'event',
        'url',
        'secret',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }
}
