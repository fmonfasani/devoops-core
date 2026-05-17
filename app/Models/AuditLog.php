<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Audit log entry',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'organization_id' => ['type' => 'integer', 'nullable' => true],
        'team_id' => ['type' => 'integer', 'nullable' => true],
        'user_id' => ['type' => 'integer', 'nullable' => true],
        'event' => ['type' => 'string'],
        'auditable_type' => ['type' => 'string', 'nullable' => true],
        'auditable_id' => ['type' => 'integer', 'nullable' => true],
        'description' => ['type' => 'string', 'nullable' => true],
        'old_values' => ['type' => 'object', 'nullable' => true],
        'new_values' => ['type' => 'object', 'nullable' => true],
        'ip_address' => ['type' => 'string', 'nullable' => true],
        'created_at' => ['type' => 'string'],
    ]
)]
class AuditLog extends Model
{
    protected $fillable = [
        'organization_id',
        'team_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
