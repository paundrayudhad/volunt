# Phase 8: Core Volunteer & Public REST API (Sanctum) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan REST API v1 stateless berbasis Laravel Sanctum untuk Public Event Discovery, Volunteer Registration, Riwayat Pendaftaran Volunteer, dan Verifikasi Publik Sertifikat dengan proteksi rate limiting terpadu, fail-closed multi-tenant isolation, dan penggunaan kembali service layer.

**Architecture:** Menerapkan routing `/api/v1/*` terpisah di `routes/api.php` dengan controller API ramping di `App\Http\Controllers\Api\V1\` yang mendelegasikan business rules ke `RegistrationService`, `QuotaService`, `CertificateService`, dan `AuditLogService`. Serialisasi respons distandarisasi menggunakan Laravel `JsonResource` terdedikasi.

**Tech Stack:** Laravel 13, Laravel Sanctum, PHP 8.4, PostgreSQL 16, Pest PHP, Laravel Pint, Larastan (PHPStan Level 5).

**Spec:** `docs/superpowers/specs/2026-09-26-phase8-rest-api-sanctum-design.md`

## Global Constraints

- **Stateless & Secure**: Seluruh endpoint terproteksi menggunakan token Sanctum Bearer (`auth:sanctum`), kecuali endpoint katalog publik (`/api/v1/events/*`) dan verifikasi sertifikat (`/api/v1/certificates/*`).
- **Fail-Closed Privacy**: Resource milik user/organisasi lain wajib menghasilkan HTTP 404 (bukan 403 atau data bocor).
- **Service Reuse**: Logika pendaftaran dan pembatalan wajib memanggil `RegistrationService` dan `QuotaService` untuk menjamin integritas kuota dan audit trail.
- **Idempotency & Rate Limiting**: Endpoint submit pendaftaran mendukung `Idempotency-Key` dan dibatasi named rate limiters (`auth`, `public-api`, `registration-submit`).
- **Quality Gates**: Setiap task wajib lulus Pint, Larastan Level 5, dan Pest test suite.

---

### Task 1: Sanctum Installation, User Token Setup & API Routing Configuration

**Files:**
- Modify: `composer.json` (jika `laravel/sanctum` belum terpasang)
- Modify: `app/Models/User.php`
- Modify: `bootstrap/app.php`
- Create: `routes/api.php`
- Test: `tests/Feature/Api/V1/ApiRoutingTest.php`

**Interfaces:**
- Consumes: Model `User`, HTTP kernel routing
- Produces: Trait `HasApiTokens` pada `User`, file `routes/api.php` terdaftar dengan prefix `/api/v1`

- [ ] **Step 1: Tulis failing test untuk routing API v1**

```php
<?php

it('merespons health check atau ping route pada api v1', function () {
    $response = $this->getJson('/api/v1/ping');

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok', 'version' => 'v1']);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan failing**

Run: `vendor/bin/pest tests/Feature/Api/V1/ApiRoutingTest.php`
Expected: FAIL dengan 404 Not Found

- [ ] **Step 3: Implementasikan konfigurasi Sanctum dan API routes**

Update `app/Models/User.php` untuk menambahkan `Laravel\Sanctum\HasApiTokens`.
Update `bootstrap/app.php` untuk mendaftarkan `api: __DIR__.'/../routes/api.php'`.
Buat `routes/api.php` dengan prefix group `/api/v1`.

- [ ] **Step 4: Jalankan test untuk memastikan passing**

Run: `vendor/bin/pest tests/Feature/Api/V1/ApiRoutingTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php bootstrap/app.php routes/api.php tests/Feature/Api/V1/ApiRoutingTest.php
git commit -m "feat(api): konfigurasi sanctum dan routing api v1"
```

---

### Task 2: Authentication Endpoints (`/api/v1/auth/*`)

**Files:**
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Create: `app/Http/Requests/Api/V1/LoginRequest.php`
- Create: `app/Http/Resources/Api/V1/UserResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/AuthApiTest.php`

**Interfaces:**
- Consumes: Kredensial email & password
- Produces: Bearer API token via Sanctum, User profile resource, Revokasi token

- [ ] **Step 1: Tulis failing test untuk Auth API (Login, Me, Logout)**

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Berhasil logout.']);

    expect($user->tokens()->count())->toBe(0);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan failing**

Run: `vendor/bin/pest tests/Feature/Api/V1/AuthApiTest.php`
Expected: FAIL

- [ ] **Step 3: Implementasikan AuthController, Request, dan Resource**

Buat `LoginRequest` dengan validasi email dan password.
Buat `UserResource` untuk memformat data user (tanpa password/remember_token).
Implementasikan method `login()`, `me()`, dan `logout()` pada `AuthController`.
Daftarkan route di `routes/api.php` di bawah prefix `/api/v1/auth`.

- [ ] **Step 4: Jalankan test untuk memastikan passing**

Run: `vendor/bin/pest tests/Feature/Api/V1/AuthApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/AuthController.php app/Http/Requests/Api/V1/LoginRequest.php app/Http/Resources/Api/V1/UserResource.php routes/api.php tests/Feature/Api/V1/AuthApiTest.php
git commit -m "feat(api): endpoint autentikasi sanctum login me logout"
```

---

### Task 3: Public Event Discovery Endpoints (`/api/v1/events/*`)

**Files:**
- Create: `app/Http/Controllers/Api/V1/EventController.php`
- Create: `app/Http/Resources/Api/V1/EventListResource.php`
- Create: `app/Http/Resources/Api/V1/EventDetailResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/EventApiTest.php`

**Interfaces:**
- Consumes: Query params (`q`, `category`, `city`, `per_page`), Event slug
- Produces: List event terpublikasi (paginated), Detail event publik beserta roles & shifts

- [ ] **Step 1: Tulis failing test untuk Event Discovery API**

```php
<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hanya menampilkan event yang berstatus published pada list publik', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $published = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Event Publik Terbuka',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $draft = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Event Internal Draft',
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/v1/events');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.title', 'Event Publik Terbuka');
});

it('dapat menampilkan detail event publik beserta role dan shift', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'name' => 'Liaison Officer',
        'quota' => 10,
    ]);

    $response = $this->getJson('/api/v1/events/'.$event->slug);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.roles.0.name', 'Liaison Officer');
});

it('mengembalikan 404 pada event yang masih draft atau tidak ditemukan', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $draft = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/v1/events/'.$draft->slug);

    $response->assertStatus(404);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan failing**

Run: `vendor/bin/pest tests/Feature/Api/V1/EventApiTest.php`
Expected: FAIL

- [ ] **Step 3: Implementasikan EventController dan Resources**

Buat `EventListResource` dan `EventDetailResource`.
Implementasikan method `index()` (dengan query filter & pagination) dan `show()` (hanya published event) pada `EventController`.
Daftarkan route publik di `routes/api.php` dengan middleware `throttle:public-api`.

- [ ] **Step 4: Jalankan test untuk memastikan passing**

Run: `vendor/bin/pest tests/Feature/Api/V1/EventApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/EventController.php app/Http/Resources/Api/V1/EventListResource.php app/Http/Resources/Api/V1/EventDetailResource.php routes/api.php tests/Feature/Api/V1/EventApiTest.php
git commit -m "feat(api): endpoint katalog dan detail event publik"
```

---

### Task 4: Volunteer Registration & History Endpoints (`/api/v1/events/{slug}/register` & `/api/v1/my/registrations/*`)

**Files:**
- Create: `app/Http/Controllers/Api/V1/RegistrationController.php`
- Create: `app/Http/Requests/Api/V1/SubmitRegistrationRequest.php`
- Create: `app/Http/Resources/Api/V1/RegistrationResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/RegistrationApiTest.php`

**Interfaces:**
- Consumes: Sanctum user, Event slug, `SubmitRegistrationRequest` (role_id, answers), `Idempotency-Key` header
- Produces: Registration model via `RegistrationService`, list pendaftaran pribadi, pembatalan pendaftaran (withdraw)

- [ ] **Step 1: Tulis failing test untuk Registration API**

```php
<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\QuotaService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dapat mendaftar sebagai volunteer pada event publik melalui API', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'registration_open',
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
    ]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'name' => 'Logistik',
        'quota' => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/events/'.$event->slug.'/register', [
        'event_role_id' => $role->id,
        'answers' => [],
    ], [
        'Idempotency-Key' => 'test-idem-key-123',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.event.title', $event->title);

    $this->assertDatabaseHas('registrations', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
    ]);
});

it('dapat melihat riwayat pendaftaran milik sendiri', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    $myReg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => 'my-key-1',
    ]));

    $otherReg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $otherUser->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => 'other-key-2',
    ]));

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my/registrations');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myReg->id);
});

it('dapat membatalkan pendaftaran pending milik sendiri dan 403/404 jika milik orang lain', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'registration_open']);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    $reg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => 'my-key-cancel',
    ]));

    // Coba batalkan milik orang lain -> 404 fail-closed
    $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/v1/my/registrations/'.$reg->id.'/withdraw')
        ->assertStatus(404);

    // Batalkan milik sendiri -> 200 OK
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/my/registrations/'.$reg->id.'/withdraw')
        ->assertStatus(200);

    expect($reg->fresh()->status)->toBe('withdrawn');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan failing**

Run: `vendor/bin/pest tests/Feature/Api/V1/RegistrationApiTest.php`
Expected: FAIL

- [ ] **Step 3: Implementasikan RegistrationController dan Resource**

Buat `RegistrationResource` untuk formatting payload data registrasi.
Buat `SubmitRegistrationRequest`.
Implementasikan method `register()`, `index()`, dan `withdraw()` di `RegistrationController` dengan memanggil `RegistrationService`.
Daftarkan routes di `routes/api.php` dengan middleware `auth:sanctum` dan `throttle:registration-submit`.

- [ ] **Step 4: Jalankan test untuk memastikan passing**

Run: `vendor/bin/pest tests/Feature/Api/V1/RegistrationApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/RegistrationController.php app/Http/Requests/Api/V1/SubmitRegistrationRequest.php app/Http/Resources/Api/V1/RegistrationResource.php routes/api.php tests/Feature/Api/V1/RegistrationApiTest.php
git commit -m "feat(api): endpoint registrasi volunteer dan kelola pendaftaran diri"
```

---

### Task 5: Public Certificate Verification Endpoint (`/api/v1/certificates/verify/{no}`)

**Files:**
- Create: `app/Http/Controllers/Api/V1/CertificateVerificationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CertificateApiTest.php`

**Interfaces:**
- Consumes: Nomor sertifikat (`{no}`)
- Produces: JSON payload verifikasi sertifikat (Valid, Nama, Event, Org, Role, Tanggal Terbit) tanpa membocorkan PII

- [ ] **Step 1: Tulis failing test untuk Certificate Verification API**

```php
<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('memverifikasi sertifikat yang valid dan mengembalikan data publik minimal tanpa PII', function () {
    $org = Organization::factory()->create(['status' => 'active', 'name' => 'Organisasi Sukarelawan']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'title' => 'Bakti Sosial']);
    $role = EventRole::factory()->create(['event_id' => $event->id, 'name' => 'Koordinator Lapangan']);
    $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi.secret@example.com']);

    $cert = Certificate::unguarded(fn () => Certificate::create([
        'certificate_number' => 'CERT-2026-999',
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'status' => 'issued',
        'issued_at' => now(),
    ]));

    $response = $this->getJson('/api/v1/certificates/verify/CERT-2026-999');

    $response->assertStatus(200)
        ->assertJson([
            'valid' => true,
            'certificate' => [
                'certificate_number' => 'CERT-2026-999',
                'recipient_name' => 'Budi Santoso',
                'event_title' => 'Bakti Sosial',
                'organization_name' => 'Organisasi Sukarelawan',
                'role_name' => 'Koordinator Lapangan',
            ],
        ]);

    // Pastikan email tidak bocor
    expect($response->getContent())->not->toContain('budi.secret@example.com');
});

it('mengembalikan 404 pada nomor sertifikat yang tidak ditemukan atau berstatus revoked', function () {
    $response = $this->getJson('/api/v1/certificates/verify/CERT-INVALID-000');

    $response->assertStatus(404)
        ->assertJson(['valid' => false, 'message' => 'Sertifikat tidak valid atau tidak ditemukan.']);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan failing**

Run: `vendor/bin/pest tests/Feature/Api/V1/CertificateApiTest.php`
Expected: FAIL

- [ ] **Step 3: Implementasikan CertificateVerificationController**

Implementasikan `verify($no)` yang memanggil query `Certificate` (status `issued`) dan memformat payload JSON aman.
Daftarkan route publik `/api/v1/certificates/verify/{no}` dengan middleware `throttle:public-api`.

- [ ] **Step 4: Jalankan test untuk memastikan passing**

Run: `vendor/bin/pest tests/Feature/Api/V1/CertificateApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/CertificateVerificationController.php routes/api.php tests/Feature/Api/V1/CertificateApiTest.php
git commit -m "feat(api): endpoint verifikasi sertifikat publik"
```

---

### Task 6: Final Verification, Quality Gates & Documentation

**Files:**
- Modify: `API.md`
- Test: Full Test Suite (`vendor/bin/pest`)
- Code Quality: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`

- [ ] **Step 1: Jalankan seluruh test suite API dan web**

Run: `vendor/bin/pest`
Expected: Semua test (Phase 1–8) PASS 100%

- [ ] **Step 2: Jalankan static analysis dan formatter**

Run: `vendor/bin/pint --test`
Run: `vendor/bin/phpstan analyse`
Expected: 0 errors

- [ ] **Step 3: Perbarui dokumentasi API.md dengan status implementasi Phase 8**

Update `API.md` mencantumkan rincian endpoint yang telah aktif pada `/api/v1/*`.

- [ ] **Step 4: Commit**

```bash
git add API.md
git commit -m "docs: perbarui status implementasi api rest v1 sanctum"
```
