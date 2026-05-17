<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations->contains('id', $organization->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        if (! $user->organizations->contains('id', $organization->id)) {
            return false;
        }

        $role = $this->getRole($user, $organization);

        return $role === 'owner' || $role === 'admin';
    }

    public function delete(User $user, Organization $organization): bool
    {
        if (! $user->organizations->contains('id', $organization->id)) {
            return false;
        }

        return $organization->owner_id === $user->id;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        if (! $user->organizations->contains('id', $organization->id)) {
            return false;
        }

        $role = $this->getRole($user, $organization);

        return $role === 'owner' || $role === 'admin';
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        if (! $user->organizations->contains('id', $organization->id)) {
            return false;
        }

        $role = $this->getRole($user, $organization);

        return $role === 'owner' || $role === 'admin';
    }

    private function getRole(User $user, Organization $organization): ?string
    {
        $org = $user->organizations->where('id', $organization->id)->first();

        return data_get($org, 'pivot.role');
    }
}
