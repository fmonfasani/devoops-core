<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    protected $fillable = [
        'organization_id',
        'team_id',
        'meter',
        'quantity',
        'unit',
        'description',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function scopeByMeter($query, string $meter)
    {
        return $query->where('meter', $meter);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }
}
