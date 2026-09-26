<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('dapat melakukan login dengan kredensial valid dan mengembalikan sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'volunteer@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'volunteer@example.com',
        'password' => 'password123',
        'device_name' => 'Mobile App',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'name', 'email'],
        ]);
});

it('menolak login dengan kredensial salah dengan 401', function () {
    $user = User::factory()->create([
        'email' => 'volunteer@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'volunteer@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Kredensial tidak cocok dengan catatan kami.']);
});

it('dapat mengambil profil diri sendiri via /api/v1/auth/me', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('dapat melakukan logout dan mencabut token', function () {
    $user = User::factory()->create();
    $tokenObj = $user->createToken('test-token');
    $token = $tokenObj->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Berhasil logout.']);

    expect(PersonalAccessToken::findToken($token))->toBeNull();
});
