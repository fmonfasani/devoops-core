<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();

    $this->organization = Organization::factory()->create([
        'owner_id' => $this->owner->id,
    ]);

    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();

    $this->organization->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->organization->members()->attach($this->admin->id, ['role' => 'admin']);
    $this->organization->members()->attach($this->member->id, ['role' => 'member']);
});

describe('update permission', function () {
    test('owner can update organization', function () {
        $this->actingAs($this->owner);
        expect($this->owner->can('update', $this->organization))->toBeTrue();
    });

    test('admin can update organization', function () {
        $this->actingAs($this->admin);
        expect($this->admin->can('update', $this->organization))->toBeTrue();
    });

    test('member cannot update organization', function () {
        $this->actingAs($this->member);
        expect($this->member->can('update', $this->organization))->toBeFalse();
    });

    test('non-member cannot update organization', function () {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        expect($outsider->can('update', $this->organization))->toBeFalse();
    });
});

describe('delete permission', function () {
    test('owner can delete organization', function () {
        $this->actingAs($this->owner);
        expect($this->owner->can('delete', $this->organization))->toBeTrue();
    });

    test('admin cannot delete organization', function () {
        $this->actingAs($this->admin);
        expect($this->admin->can('delete', $this->organization))->toBeFalse();
    });

    test('member cannot delete organization', function () {
        $this->actingAs($this->member);
        expect($this->member->can('delete', $this->organization))->toBeFalse();
    });

    test('non-member cannot delete organization', function () {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        expect($outsider->can('delete', $this->organization))->toBeFalse();
    });
});

describe('manageMembers permission', function () {
    test('owner can manage members', function () {
        $this->actingAs($this->owner);
        expect($this->owner->can('manageMembers', $this->organization))->toBeTrue();
    });

    test('admin can manage members', function () {
        $this->actingAs($this->admin);
        expect($this->admin->can('manageMembers', $this->organization))->toBeTrue();
    });

    test('member cannot manage members', function () {
        $this->actingAs($this->member);
        expect($this->member->can('manageMembers', $this->organization))->toBeFalse();
    });

    test('non-member cannot manage members', function () {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        expect($outsider->can('manageMembers', $this->organization))->toBeFalse();
    });
});

describe('manageBilling permission', function () {
    test('owner can manage billing', function () {
        $this->actingAs($this->owner);
        expect($this->owner->can('manageBilling', $this->organization))->toBeTrue();
    });

    test('admin can manage billing', function () {
        $this->actingAs($this->admin);
        expect($this->admin->can('manageBilling', $this->organization))->toBeTrue();
    });

    test('member cannot manage billing', function () {
        $this->actingAs($this->member);
        expect($this->member->can('manageBilling', $this->organization))->toBeFalse();
    });

    test('non-member cannot manage billing', function () {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        expect($outsider->can('manageBilling', $this->organization))->toBeFalse();
    });
});

describe('view permission', function () {
    test('owner can view organization', function () {
        $this->actingAs($this->owner);
        expect($this->owner->can('view', $this->organization))->toBeTrue();
    });

    test('admin can view organization', function () {
        $this->actingAs($this->admin);
        expect($this->admin->can('view', $this->organization))->toBeTrue();
    });

    test('member can view organization', function () {
        $this->actingAs($this->member);
        expect($this->member->can('view', $this->organization))->toBeTrue();
    });

    test('non-member cannot view organization', function () {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        expect($outsider->can('view', $this->organization))->toBeFalse();
    });
});
