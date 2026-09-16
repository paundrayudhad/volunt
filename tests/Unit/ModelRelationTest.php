<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('menghubungkan user, organisasi, dan membership', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    expect($user->belongsToOrganization($org->id))->toBeTrue()
        ->and($user->organizationRole($org->id))->toBe('owner')
        ->and($org->members)->toHaveCount(1);
});

it('menolak duplikat membership via unique constraint', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $data = [
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ];
    OrganizationMember::unguarded(fn () => OrganizationMember::create($data));

    expect(fn () => OrganizationMember::unguarded(fn () => OrganizationMember::create($data)))
        ->toThrow(QueryException::class);
});

it('mencegah role invalid via check constraint', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    expect(fn () => OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => 'raja',
        'status' => 'active',
        'joined_at' => now(),
    ])))->toThrow(QueryException::class);
});
