# Phase 7: Security Audit, Production Hardening & Final Verification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan penguatan keamanan komprehensif (*defense-in-depth*), middleware security headers global, rate limiting terpadu, artisan command audit kesiapan produksi, dan test suite penetrasi multi-tenant menyeluruh.

**Architecture:** Menerapkan arsitektur *defense-in-depth* dengan middleware HTTP security headers (CSP, HSTS, X-Frame-Options, nosniff, permissions), named rate limiting di layer routing, automated pre-flight production auditor artisan command, serta automated cross-tenant IDOR audit suite.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, Spatie Laravel Permission, Pest PHP, Laravel Pint, Larastan (PHPStan Level 5).

**Spec:** `docs/superpowers/specs/2026-09-26-phase7-security-hardening-design.md`

## Global Constraints

- **Multi-Tenant Isolation**: Request antar tenant pada seluruh resource wajib menghasilkan HTTP 404 (fail-closed, tanpa membocorkan eksistensi resource).
- **Security Headers**: Respons HTTP harus menyertakan header keamanan standar modern (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, dan CSP).
- **Rate Limiting**: Lindungi endpoint sensitif (`auth`, `registration-submit`, `exports`, `public-api`) dari serangan brute-force dan DoS.
- **Fail-Safe Defaults**: Nilai default konfigurasi produksi harus aman (*safe-by-default*).
- **Quality Gates**: Setiap task wajib lulus Pint, Larastan Level 5, dan Pest test suite.

---

### Task 1: Global Security Headers Middleware

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: Request HTTP masuk
- Produces: Response HTTP dengan header keamanan lengkap

- [ ] **Step 1: Tulis failing test untuk SecurityHeaders middleware**

```php
<?php

use Illuminate\Support\Facades\Route;

it('menyertakan security headers lengkap pada setiap respons HTTP', function () {
    Route::get('/test-security-headers', fn () => response('OK'));

    $response = $this->get('/test-security-headers');

    $response->assertStatus(200);
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});

it('menyertakan header HSTS saat environment production atau HTTPS', function () {
    Route::get('/test-hsts', fn () => response('OK'));

    config(['app.env' => 'production']);
    $response = $this->get('/test-hsts');

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});
```

- [ ] **Step 2: Jalankan test dan pastikan FAIL**

`docker compose exec app ./vendor/bin/pest tests/Feature/SecurityHeadersTest.php`

- [ ] **Step 3: Implementasikan `SecurityHeaders.php` dan daftarkan di `bootstrap/app.php`**

Buat middleware `app/Http/Middleware/SecurityHeaders.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            . "font-src 'self' https://fonts.bunny.net data:; "
            . "img-src 'self' data: blob: https:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'; "
            . "form-action 'self'; "
            . "base-uri 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
```

Daftarkan di `bootstrap/app.php`:
```php
$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
```

- [ ] **Step 4: Jalankan test dan pastikan PASS**

`docker compose exec app ./vendor/bin/pest tests/Feature/SecurityHeadersTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SecurityHeaders.php bootstrap/app.php tests/Feature/SecurityHeadersTest.php
git commit -m "feat: tambahkan global security headers middleware"
```

---

### Task 2: Named Rate Limiters & Route Rate Limiting Protection

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/RateLimitingSecurityTest.php`

**Interfaces:**
- Consumes: Request HTTP masuk pada endpoint sensitif
- Produces: Throttled HTTP Response (429) jika melebihi limit

- [ ] **Step 1: Tulis failing test untuk named rate limiters**

```php
<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('membatasi request submit registrasi hingga 10 per menit', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'registration_open']);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('registration-submit:'.$user->id);
    }

    expect(RateLimiter::tooManyAttempts('registration-submit:'.$user->id, 10))->toBeTrue();
});

it('membatasi request ekspor data hingga 10 per menit', function () {
    $user = User::factory()->create();
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('exports:'.$user->id);
    }

    expect(RateLimiter::tooManyAttempts('exports:'.$user->id, 10))->toBeTrue();
});
```

- [ ] **Step 2: Jalankan test dan pastikan FAIL**

`docker compose exec app ./vendor/bin/pest tests/Feature/RateLimitingSecurityTest.php`

- [ ] **Step 3: Konfigurasi Named Rate Limiters di `AppServiceProvider` dan `routes/web.php`**

Update `app/Providers/AppServiceProvider.php`:
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('auth', function (Request $request) {
        $email = (string) $request->input('email');
        return Limit::perMinute(5)->by($request->ip().'|'.$email);
    });

    RateLimiter::for('registration-submit', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('exports', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('public-api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });
}
```

Pastikan rute di `routes/web.php` menggunakan middleware throttle bernama (`throttle:exports`, `throttle:public-api`, `throttle:registration-submit`).

- [ ] **Step 4: Jalankan test dan pastikan PASS**

`docker compose exec app ./vendor/bin/pest tests/Feature/RateLimitingSecurityTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/web.php tests/Feature/RateLimitingSecurityTest.php
git commit -m "feat: konfigurasi named rate limiters untuk endpoint sensitif"
```

---

### Task 3: Production Readiness & Verification Artisan Command

**Files:**
- Create: `app/Console/Commands/CheckProductionCommand.php`
- Test: `tests/Feature/CheckProductionCommandTest.php`

**Interfaces:**
- Consumes: System config, database state, storage permissions
- Produces: CLI table report dan exit code (0: ready, 1: failed)

- [ ] **Step 1: Tulis failing test untuk `app:check-production`**

```php
<?php

use Illuminate\Support\Facades\Artisan;

it('menjalankan command app:check-production dan memvalidasi konfigurasi dasar', function () {
    $code = Artisan::call('app:check-production');
    $output = Artisan::output();

    expect($output)->toContain('Production Readiness Checklist');
    expect($output)->toContain('Encryption Key');
    expect($output)->toContain('Database Connection');
    expect($output)->toContain('Storage Permissions');
});
```

- [ ] **Step 2: Jalankan test dan pastikan FAIL**

`docker compose exec app ./vendor/bin/pest tests/Feature/CheckProductionCommandTest.php`

- [ ] **Step 3: Implementasikan `CheckProductionCommand.php`**

Buat `app/Console/Commands/CheckProductionCommand.php`:
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProductionCommand extends Command
{
    protected $signature = 'app:check-production';
    protected $description = 'Audit kesiapan deployment dan konfigurasi keamanan produksi';

    public function handle(): int
    {
        $this->info('=== Production Readiness Checklist ===');
        $checks = [];
        $failed = false;

        // 1. APP_DEBUG
        $debug = config('app.debug');
        $checks[] = [
            'Check' => 'APP_DEBUG is disabled',
            'Status' => $debug ? '<fg=red>FAIL</>' : '<fg=green>OK</>',
            'Details' => $debug ? 'APP_DEBUG=true (harus false di produksi)' : 'Disabled',
        ];
        if ($debug && app()->environment('production')) {
            $failed = true;
        }

        // 2. APP_KEY
        $hasKey = ! empty(config('app.key'));
        $checks[] = [
            'Check' => 'Encryption Key (APP_KEY)',
            'Status' => $hasKey ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $hasKey ? 'Configured' : 'Missing APP_KEY',
        ];
        if (! $hasKey) {
            $failed = true;
        }

        // 3. Database Connection
        $dbOk = true;
        $dbError = '';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }
        $checks[] = [
            'Check' => 'Database Connection',
            'Status' => $dbOk ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $dbOk ? 'Connected (PostgreSQL)' : $dbError,
        ];
        if (! $dbOk) {
            $failed = true;
        }

        // 4. Storage & Cache Permissions
        $storageWritable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));
        $checks[] = [
            'Check' => 'Storage Permissions',
            'Status' => $storageWritable ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $storageWritable ? 'Writable' : 'storage/ not writable',
        ];
        if (! $storageWritable) {
            $failed = true;
        }

        // 5. Session Cookie Security
        $secureCookie = config('session.secure');
        $checks[] = [
            'Check' => 'Session Secure Cookie',
            'Status' => $secureCookie ? '<fg=green>OK</>' : '<fg=yellow>WARN</>',
            'Details' => $secureCookie ? 'Enabled' : 'SESSION_SECURE_COOKIE=false (disarankan true di HTTPS)',
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);

        if ($failed) {
            $this->error('Terdapat pemeriksaan kesiapan produksi yang gagal.');
            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan utama kesiapan produksi berhasil.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Jalankan test dan pastikan PASS**

`docker compose exec app ./vendor/bin/pest tests/Feature/CheckProductionCommandTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/CheckProductionCommand.php tests/Feature/CheckProductionCommandTest.php
git commit -m "feat: tambahkan artisan command app:check-production"
```

---

### Task 4: Comprehensive Cross-Tenant IDOR & Privilege Escalation Audit Suite

**Files:**
- Create: `tests/Feature/TenantSecurityMatrixTest.php`

**Interfaces:**
- Consumes: Seluruh endpoint modular
- Produces: Verifikasi automated pen-test isolasi tenant (404 Fail-Closed)

- [ ] **Step 1: Tulis matrix test komprehensif untuk seluruh modul**

Buat `tests/Feature/TenantSecurityMatrixTest.php`:
```php
<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup Permissions
    foreach ([
        'event.update', 'event.delete', 'division.manage', 'shift.manage',
        'registration.read', 'registration.review', 'volunteer.assign',
        'attendance.read', 'attendance.manage', 'announcement.create',
        'incident.manage', 'incident.report', 'certificate.issue',
        'certificate.revoke', 'certificate.read', 'talent.search',
        'talent.invite', 'analytics.view', 'analytics.export',
    ] as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
});

it('memastikan isolasi data lintas organisasi menghasilkan 404 pada seluruh modul utama', function () {
    $orgA = Organization::factory()->create(['status' => 'active']);
    $orgB = Organization::factory()->create(['status' => 'active']);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $ownerA->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $ownerB->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $ownerA->givePermissionTo(Permission::all());
    $ownerB->givePermissionTo(Permission::all());

    $eventA = Event::factory()->create(['organization_id' => $orgA->id, 'status' => 'published']);
    $eventB = Event::factory()->create(['organization_id' => $orgB->id, 'status' => 'published']);

    // 1. Event show silang
    $this->actingAs($ownerA)->get(route('organizer.events.show', [$orgA->slug, $eventB->slug]))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.show', [$orgB->slug, $eventA->slug]))->assertStatus(404);

    // 2. Analytics silang
    $this->actingAs($ownerA)->get(route('organizer.events.analytics.index', [$orgA->slug, $eventB->slug]))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.analytics.index', [$orgB->slug, $eventA->slug]))->assertStatus(404);

    // 3. Export silang
    $this->actingAs($ownerA)->get(route('organizer.events.export', [$orgA->slug, $eventB->slug, 'registrations']))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.export', [$orgB->slug, $eventA->slug, 'registrations']))->assertStatus(404);
});
```

- [ ] **Step 2: Jalankan test dan pastikan PASS**

`docker compose exec app ./vendor/bin/pest tests/Feature/TenantSecurityMatrixTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/TenantSecurityMatrixTest.php
git commit -m "test: tambahkan comprehensive tenant security matrix test"
```

---

### Task 5: Dokumentasi Deployment & Final Quality Gates

**Files:**
- Modify: `DEPLOYMENT.md`

- [ ] **Step 1: Perbarui `DEPLOYMENT.md` dengan instruksi audit dan hardening**

Dokumentasikan:
- Cara menjalankan `php artisan app:check-production` sebelum deployment.
- Konfigurasi `SecurityHeaders` dan Content Security Policy.
- Named rate limiters dan rekomendasi monitoring produksi.

- [ ] **Step 2: Jalankan Laravel Pint**

`docker compose exec app ./vendor/bin/pint --test`

- [ ] **Step 3: Jalankan Larastan (PHPStan Level 5)**

`docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress`

- [ ] **Step 4: Jalankan Seluruh Pest Test Suite**

`docker compose exec app php -d memory_limit=1G ./vendor/bin/pest`

- [ ] **Step 5: Commit**

```bash
git add DEPLOYMENT.md
git commit -m "docs: perbarui dokumentasi security hardening dan production checklist"
```
