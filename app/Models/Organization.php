<?php

namespace App\Models;

use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Organization model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer', 'description' => 'The unique identifier of the organization.'],
        'name' => ['type' => 'string', 'description' => 'The name of the organization.'],
        'slug' => ['type' => 'string', 'description' => 'The unique slug of the organization.'],
        'description' => ['type' => 'string', 'description' => 'The description of the organization.'],
        'logo_url' => ['type' => 'string', 'description' => 'The URL of the organization logo.'],
        'billing_email' => ['type' => 'string', 'description' => 'The billing email of the organization.'],
        'owner_id' => ['type' => 'integer', 'description' => 'The ID of the user who owns the organization.'],
        'created_at' => ['type' => 'string', 'description' => 'The date and time the organization was created.'],
        'updated_at' => ['type' => 'string', 'description' => 'The date and time the organization was last updated.'],
        'deleted_at' => ['type' => 'string', 'description' => 'The date and time the organization was deleted.'],
        'members' => new OA\Property(
            property: 'members',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/User'),
            description: 'The members of the organization.'
        ),
        'teams' => new OA\Property(
            property: 'teams',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Team'),
            description: 'The teams in the organization.'
        ),
    ]
)]
class Organization extends Model
{
    use HasFactory, HasSafeStringAttribute, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'billing_email',
        'owner_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted()
    {
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });

        static::deleting(function (Organization $organization) {
            if ($organization->isForceDeleting()) {
                $organization->teams()->update(['organization_id' => null]);
                $organization->members()->detach();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user', 'organization_id', 'user_id')->withPivot('role')->withTimestamps();
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
