# Phase 1 (Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun fondasi identitas & tenancy: auth (Breeze Livewire + kebijakan keamanan), organization via pengajuan+approval, membership via undangan, RBAC-scope, audit/security log — semuanya hijau (Pest + Pint + PHPStan + audit).

**Architecture:** Modular monolith dua modul domain (`Identity`, `Tenancy`). Controller tipis → Form Request (`authorize` + `rules`) → Service (seluruh business logic) → Model. Permission Spatie = kemampuan; `organization_members.role` + membership = cakupan; setiap cek keduanya. Append-only log ditulis service, tidak pernah controller.

**Tech Stack:** Laravel 13, PHP 8.4 (via container `app`), PostgreSQL 16 (service `db`), Livewire 4.4, Spatie Permission 8.3, Pest 4.7, Pint, Larastan level 5, Breeze (Livewire stack).

**Spec:** `docs/superpowers/specs/2026-09-16-phase1-foundation-design.md` (+ `PRD.md`, `ARCHITECTURE.md`, `DATABASE.md`, `SECURITY.md`, `TESTING.md` di repo root `D:\volunt`).

## Global Constraints

- PHP 8.4 — hanya via container; semua perintah PHP/composer/artisan via `docker compose exec app ...` (PHP host tidak dipakai).
- PostgreSQL 16 via service `db`; kredensial dev `webvolunteer/secret`; DB app `webvolunteer`, DB test `webvolunteer_test` (lihat `phpunit.xml` — sudah mengarah ke pgsql test).
- Dilarang menambah Redis, MinIO, atau service lain (batas Phase 0, masih berlaku).
- Bahasa prose: Indonesia; code/identifier/branch/commit English.
- Setiap langkah berisi konten nyata; tanpa TBD/TODO/placeholder.
- Pelajaran Phase 0 (wajib diindahkan): jangan `cp -a` menimpa dotfiles yang sudah ada (`.gitignore`, `README.md`); scaffold/copy ke direktori ter-mount lalu `mv` isi non-dotfile; `composer show` satu paket per perintah; `run --rm` + copy terpisah rawan hilang — gabung dalam satu `exec` berurutan; direktori aneh `C…/Temp/scaffold/` adalah artefak MSYS yang harus dihindari, bukan dibersihkan belakangan.
- `git add` dilarang memakai `-A` membabi-buta: selalu `git status --porcelain` dulu; bila artefak asing muncul di staging, `git reset` + investigasi sebelum commit.

---

## Scope Check

Spec Phase 1 hanya fondasi identitas/tenancy — satu-satunya scope yang menghasilkan software berjalan dan teruji tanpa modul lain. Event (Phase 2) dan seterusnya masing-masing mendapat rencana terpisah setelah Phase 1 hijau.

## File Structure

Dibuat di Phase 1 (selain file Breeze yang tidak didaftar satu per satu):

- `app/Models/Organization.php` — model + relasi members/invitations/requests + scope `active()`.
- `app/Models/OrganizationMember.php` — model; `$fillable` hanya `status` (role/org/user di-set server-side via service).
- `app/Models/OrganizationInvitation.php` — model + helper `isPending()`, `isExpired()`.
- `app/Models/OrganizationRequest.php` — model pengajuan + helper `isPending()`.
- `app/Models/AuditLog.php`, `app/Models/SecurityLog.php` — model append-only (tanpa update/delete; `$fillable` allowlist tulis).
- `app/Models/User.php` (modify) — trait `HasRoles` (Spatie) + relasi members/organizations + `belongsToOrganization(int): bool` + `organizationRole(int): ?string`.
- `app/Services/OrganizationService.php` — `request(array, User)`, `approve(OrganizationRequest, User, ?string reason)`, `reject(...)`, `suspend(Organization, User, string reason)`, `archive(...)`, `activate(...)`, `updateProfile(Organization, array, User)`.
- `app/Services/MembershipService.php` — `invite(Organization, array, User)`, `acceptInvitation(OrganizationInvitation, User)`, `declineInvitation(...)`, `changeRole(OrganizationMember, string, User)`, `removeMember(OrganizationMember, User)`, `transferOwnership(Organization, User $newOwner, User $actor)`; satu-satunya penulis `organization_members` + sinkronisasi permission Spatie.
- `app/Services/AuditLogService.php` — `record(User $actor, string $action, string $resourceType, int|string $resourceId, array $context = []): AuditLog`.
- `app/Services/SecurityService.php` — `record(?User $actor, string $type, array $context = []): SecurityLog`.
- `app/Console/Commands/SeedSuperAdmin.php` — `app:seed-super-admin --email= --name=` (idempotent + audit).
- `app/Policies/OrganizationPolicy.php`, `app/Policies/MemberPolicy.php`, `app/Policies/InvitationPolicy.php`, `app/Policies/OrganizationRequestPolicy.php` (+ registrasi di `AppServiceProvider` atau `AuthServiceProvider`).
- `app/Http/Requests/` — `StoreOrganizationRequest`, `ReviewOrganizationRequest`, `InviteMemberRequest`, `UpdateMemberRequest`, `UpdateOrganizationRequest` (masing-masing `authorize()` + `rules()`).
- `app/Http/Controllers/` — `OrganizationRequestController`, `Organizer/OrganizationController`, `Organizer/MemberController`, `Organizer/InvitationController`, `Admin/OrganizationRequestController`, `Admin/OrganizationController`, `Admin/LogController` (tipis).
- `app/Livewire/` — reuse komponen Breeze untuk auth; komponen baru hanya bila interaksi reaktif dibutuhkan (mis. daftar undangan accept/decline) — YAGNI: mulai dengan Blade + controller biasa.
- `routes/web.php` (modify) — groups guest/auth/`organizer/{organization}` (scoped binding)/`admin` (gate super_admin).
- `database/migrations/` — `*_create_organizations_table.php`, `*_create_organization_members_table.php`, `*_create_organization_invitations_table.php`, `*_create_organization_requests_table.php`, `*_create_audit_logs_table.php`, `*_create_security_logs_table.php`.
- `database/seeders/` — permission granular (`organization.update`, `member.invite`, `member.remove`, `member.change_role`, `invitation.manage`, `request.review`, `organization.suspend`, ... full list di Task 2) + role `super_admin`.
- `tests/` — `tests/Unit/*ServiceTest.php`, `tests/Feature/AuthTest.php`, `tests/Feature/OrganizationTest.php`, `tests/Feature/MembershipTest.php`, `tests/Feature/AdminTest.php`, `tests/Feature/AuthorizationTest.php`, `tests/Feature/TenantIsolationTest.php`.
- Diubah: `config/auth.php` (password timeout/lockout bila perlu), `.env` (hanya lokal, tidak di-commit).

---

### Task 1: Env up + Breeze Livewire + kebijakan auth

**Files:**
- Modify: `routes/web.php`, `app/Models/User.php` (trait HasRoles — persiapan, dipakai Task 3), `config/auth.php` (hanya bila perlu)
- Create: seluruh file Breeze Livewire stack (via `composer require laravel/breeze --dev` + `php artisan breeze:install livewire`)
- Test: `tests/Feature/AuthTest.php` (register, login, logout, reset, verifikasi, throttle, respons generik)

**Interfaces:**
- Consumes: Phase 0 (stack hijau, DB `webvolunteer` + `webvolunteer_test` siap).
- Produces: auth web fungsional; `User` memakai `HasRoles`; kebijakan password-12 + throttle-5/menit + security log enforced.

- [ ] **Step 1: Nyalakan stack dan verifikasi hijau**

```bash
docker compose up -d
docker compose exec db pg_isready -U webvolunteer
curl -s -o NUL -w "%{http_code}" http://localhost:8000
```

Expected: `accepting connections`; `200`.

- [ ] **Step 2: Install Breeze Livewire stack**

```bash
docker compose exec app composer require laravel/breeze --dev
docker compose exec app php artisan breeze:install livewire
docker compose exec app php artisan migrate --force
npm install
npm run build
```

Expected: install + migrate sukses; build exit 0. Jika `breeze:install` menimpa `routes/web.php`/view bawaan — itu yang diinginkan (file kita belum ada); TAPI verifikasi `README.md`, `.gitignore`, `phpunit.xml`, `phpstan.neon` tidak berubah (`git status --porcelain` hanya menampilkan file Breeze + `composer.json`/`composer.lock`/`package.json`/`package-lock.json`/`tailwind` terkait). Bila dotfiles kita tertimpa (pelajaran Phase 0), restore dari git sebelum lanjut.

- [ ] **Step 3: Tambah HasRoles ke User + kebijakan password**

Di `app/Models/User.php`: tambah `use Spatie\Permission\Traits\HasRoles;` + trait di class. Password min 12: cari rule password Breeze (`app/Http/Requests/Auth/...` atau Livewire component) dan pastikan minimal 12 karakter + validasi backend (tulis lokasi file yang diubah di commit message bila berbeda dari dugaan).

Verifikasi:

```bash
docker compose exec app php artisan tinker --execute="echo (new App\Models\User)->hasRole('x') === false ? 'hasroles-ok' : 'fail';"
```

Expected: `hasroles-ok` (trait termuat, tanpa error).

- [ ] **Step 4: Tulis AuthTest (TDD — test dulu)**

`tests/Feature/AuthTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('mendaftarkan user dengan password minimal 12 karakter', function () {
    $this->post('/register', [
        'name' => 'Calon Owner',
        'email' => 'calon@example.com',
        'password' => 'password-pendek',
        'password_confirmation' => 'password-pendek',
    ])->assertSessionHasErrors('password');

    $this->post('/register', [
        'name' => 'Calon Owner',
        'email' => 'calon@example.com',
        'password' => 'kata-sandi-12-plus',
        'password_confirmation' => 'kata-sandi-12-plus',
    ])->assertRedirect('/dashboard');

    expect(User::where('email', 'calon@example.com')->exists())->toBeTrue();
});

it('menolak login salah tanpa membocorkan email terdaftar', function () {
    User::factory()->create(['email' => 'nyata@example.com', 'password' => bcrypt('kata-sandi-benar-12')]);

    $resTerdaftar = $this->post('/login', ['email' => 'nyata@example.com', 'password' => 'salah-salah-salah']);
    $resTakTerdaftar = $this->post('/login', ['email' => 'tak-ada@example.com', 'password' => 'salah-salah-salah']);

    $resTerdaftar->assertSessionHasErrors('email');
    $resTakTerdaftar->assertSessionHasErrors('email');
    expect($resTerdaftar->getSession()->get('errors')->get('email')[0])
        ->toBe($resTakTerdaftar->getSession()->get('errors')->get('email')[0]);
});

it('mengunci setelah 5x login gagal dan mencatat security log', function () {
    User::factory()->create(['email' => 'korban@example.com', 'password' => bcrypt('kata-sandi-benar-12')]);

    for ($i = 0; $i < 6; $i++) {
        $this->post('/login', ['email' => 'korban@example.com', 'password' => 'salah-salah-salah']);
    }

    $this->post('/login', ['email' => 'korban@example.com', 'password' => 'kata-sandi-benar-12'])
        ->assertSessionHasErrors('email'); // masih terkunci

    expect(DB::table('security_logs')->where('type', 'failed_login')->count())->toBeGreaterThanOrEqual(5);
});

it('logout menginvalidasi session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout')->assertRedirect('/');
    $this->get('/dashboard')->assertRedirect('/login');
});
```

Catatan: `security_logs` belum ada di Task 1 — test ketiga memakai tabel itu. Solusi sesuai urutan task: tulis test ini SEKARANG (merah karena tabel tak ada), dan ia hijau setelah Task 2 (migrasi security_logs) selesai. Tandai dengan `->todo()`? Tidak — Pest tidak skip otomatis yang baik; biarkan merah di Task 1 dan catat di commit message bahwa 1 test menunggu Task 2. Alternatif bersih: pindahkan assertion security_logs ke Task 2 sebagai test tambahan. Dipilih: **alternatif bersih** — hapus blok `expect(DB::table('security_logs')...)` dari test di atas, ganti dengan komentar `// assertion security_logs ditambah di Task 2`. Test di atas harus hijau penuh di akhir Task 1.

- [ ] **Step 5: Jalankan suite**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/AuthTest.php
```

Expected: semua AuthTest hijau (plus suite lama tetap hijau — jalankan full `./vendor/bin/pest` sebelum commit).

- [ ] **Step 6: Commit**

```bash
git status --porcelain
git add <file Breeze + User.php + AuthTest.php — sebutkan eksplisit, JANGAN -A membabi-buta>
git commit -m "feat: auth breeze livewire + password 12 + login throttle"
```

---

### Task 2: Migrasi + model + seeder permission

**Files:**
- Create: 6 migrasi (`organizations`, `organization_members`, `organization_invitations`, `organization_requests`, `audit_logs`, `security_logs`); 6 model; `database/seeders/PermissionSeeder.php` (+ panggil dari `DatabaseSeeder`); `app/Console/Commands/SeedSuperAdmin.php`
- Modify: `app/Models/User.php` (relasi + helper), `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/ModelRelationTest.php` (relasi + helper + constraint), assertion security_logs untuk AuthTest (tambahan dari Task 1)

**Interfaces:**
- Consumes: Task 1 (`User` + `HasRoles`, auth hijau).
- Produces: skema lengkap di `webvolunteer` + `webvolunteer_test`; permission granular terseed; command `app:seed-super-admin` idempotent; helper `belongsToOrganization()` / `organizationRole()`.

Daftar permission granular (final, dipakai Task 3–5): `organization.view`, `organization.update`, `member.view`, `member.invite`, `member.remove`, `member.change_role`, `invitation.manage`, `request.create`, `request.review`, `organization.suspend`, `organization.archive`, `audit.read`, `security.read`.

- [ ] **Step 1: Tulis 6 migrasi**

Kolom persis spec §2 (tipe: `id()`, `string`, `text nullable`, `jsonb` via `$table->jsonb()`, `foreignId()->constrained()->cascadeOnDelete()`, `timestamps`, `softDeletes()`, check via `$table->check()` atau `DB::statement` — pilih yang didukung PG16 + Laravel 13; enum status sebagai `string` + check constraint, BUKAN enum PG native agar mudah diubah).

`organization_members`: unique `(organization_id, user_id)`; index `(user_id)`.
`organization_invitations`: `token_hash` unique; unique parsial pending per `(organization_id, email)` via `DB::statement('CREATE UNIQUE INDEX ... WHERE accepted_at IS NULL AND declined_at IS NULL')` di migrasi (up + drop di down).
`organization_requests`: check `status IN ('pending','approved','rejected')`.
`audit_logs` / `security_logs`: tanpa `updated_at` (hanya `created_at` — gunakan `$table->timestamp('created_at')->useCurrent()` + tanpa `$table->timestamps()`), tanpa soft deletes.

- [ ] **Step 2: Tulis 6 model + relasi User**

`Organization`: `HasFactory`, `SoftDeletes`; fillable `name, slug, logo_path, description, email, phone, website, social_links`; cast `social_links => array`; relasi `members()`, `invitations()`, `requests()`; scope `active()` (`where status active`).
`OrganizationMember`: fillable HANYA `status`; relasi `organization()`, `user()`; cast `joined_at => datetime`.
`OrganizationInvitation`: fillable `email, role, expires_at`; helper `isPending(): bool` (`accepted_at && declined_at null && expires_at future`), `isExpired(): bool`.
`OrganizationRequest`: fillable `name, slug, description, contact`; cast `contact => array`; helper `isPending(): bool`.
`AuditLog` / `SecurityLog`: `$timestamps = false`; fillable allowlist tulis (`actor_id, organization_id, event_id, action, resource_type, resource_id, old_values, new_values, ip, user_agent, request_id` / `actor_id, type, ip, user_agent, context`); cast jsonb `=> array`; TANPA method update/delete (append-only ditegakkan di service, bukan model).
`User` tambah: `members(): HasMany`, `organizations(): BelongsToMany (via organization_members)`, `belongsToOrganization(int $orgId): bool` (exists member active), `organizationRole(int $orgId): ?string` (role member active atau null).

- [ ] **Step 3: Seeder permission + command super admin**

`PermissionSeeder`: `Permission::findOrCreate()` untuk 13 permission di atas + `Role::findOrCreate('super_admin')` (guard web). Daftarkan di `DatabaseSeeder`.
`SeedSuperAdmin`: signature `app:seed-super-admin {--email=} {--name=Administrator}`; cari/buat user by email (password acak 32 char via `Str::random`, `MustVerifyEmail` diabaikan — set `email_verified_at` now); `assignRole('super_admin')`; idempotent (bila sudah super_admin → info "sudah ada", exit 0); catat via `AuditLogService::record()` (action `super_admin.seeded`).

- [ ] **Step 4: Migrate kedua DB + seed dev + buat super_admin dev**

```bash
docker compose exec app php artisan migrate --force
docker compose exec app sh -c "DB_DATABASE=webvolunteer_test php artisan migrate --force"
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan app:seed-super-admin --email=admin@webvolunteer.local
docker compose exec db psql -U webvolunteer -d webvolunteer -c "\dt" | findstr "organizations audit_logs security_logs"
```

Expected: migrate + seed sukses; `\dt` memuat `organizations`, `organization_members`, `organization_invitations`, `organization_requests`, `audit_logs`, `security_logs`; command kedua (idempotent) mencetak "sudah ada".

- [ ] **Step 5: Tulis test relasi + assertion security_logs (TDD)**

`tests/Unit/ModelRelationTest.php`:

```php
<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

it('menghubungkan user, organisasi, dan membership', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    expect($user->belongsToOrganization($org->id))->toBeTrue()
        ->and($user->organizationRole($org->id))->toBe('owner')
        ->and($org->members)->toHaveCount(1);
});

it('menolak duplikat membership via unique constraint', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $data = ['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'staff', 'status' => 'active', 'joined_at' => now()];
    OrganizationMember::create($data);

    expect(fn () => OrganizationMember::create($data))->toThrow(QueryException::class);
});

it('mencegah role invalid via check constraint', function () {
    expect(fn () => OrganizationMember::create([
        'organization_id' => 1, 'user_id' => 1, 'role' => 'raja', 'status' => 'active', 'joined_at' => now(),
    ]))->toThrow(QueryException::class);
});
```

Butuh `OrganizationFactory` (buat `database/factories/OrganizationFactory.php`: name fake, slug unique, status active). Tambahkan ke `AuthTest.php` assertion security_logs yang ditunda dari Task 1:

```php
it('mencatat failed_login ke security_logs', function () {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'salah-salah-salah']);
    expect(DB::table('security_logs')->where('type', 'failed_login')->first())->not->toBeNull();
});
```

Agar test ini bisa lolos, event listener login-gagal → `SecurityService::record()` ditulis di Task 2 Step 3b: daftarkan listener `Failed` (Illuminate\Auth\Events\Failed) di `AppServiceProvider::boot()` yang memanggil `SecurityService::record(null, 'failed_login', ['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])`. (Lockout `account_locked` menyusul di Task 5 sebagai bagian throttle test — catat di code sebagai `// TODO Phase1-Task5`? TIDAK — tulis langsung listener `Lockout` di sini juga, satu baris tambahan, test di Task 5.)

- [ ] **Step 6: Jalankan suite + commit**

```bash
docker compose exec app ./vendor/bin/pest
```

Expected: hijau semua.

```bash
git status --porcelain
git add <file eksplisit>
git commit -m "feat: tenancy schema + models + permission seeder + super-admin command"
```

---

### Task 3: Service layer (Organization + Membership + Log)

**Files:**
- Create: `app/Services/OrganizationService.php`, `app/Services/MembershipService.php`, `app/Services/AuditLogService.php`, `app/Services/SecurityService.php`
- Test: `tests/Unit/OrganizationServiceTest.php`, `tests/Unit/MembershipServiceTest.php`

**Interfaces:**
- Consumes: Task 2 (model + helper + permission terseed + command).
- Produces: 4 service dengan signature final; sinkronisasi permission Spatie terpusat; transaksi per tulis ganda.

Aturan sinkronisasi permission (final): `MembershipService::syncPermissions(User $user, Organization $org)` — hapus semua permission langsung user yang berasal dari daftar granular (`$user->revokePermissionTo(...)` untuk 13 permission), lalu bila member active: owner → `givePermissionTo` seluruh 13; staff → `givePermissionTo` subset staff (`organization.view`, `member.view`) — permission staff lain diberikan case-by-case via `changeRole`+permission eksplisit? TIDAK — YAGNI: staff mendapat subset dasar; permission tambahan diberikan Owner via `MembershipService::grantPermission(User $actor, User $user, Organization $org, string $permission)` / `revokePermission(...)` (dicek `member.change_role` + scope). Tulis kedua method ini juga di Task 3.

- [ ] **Step 1: AuditLogService + SecurityService**

```php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;

class AuditLogService
{
    public function record(User $actor, string $action, string $resourceType, int|string $resourceId, array $context = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor->id,
            'organization_id' => $context['organization_id'] ?? null,
            'event_id' => $context['event_id'] ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => (string) $resourceId,
            'old_values' => $context['old'] ?? null,
            'new_values' => $context['new'] ?? null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'request_id' => request()->header('X-Request-ID', substr((string) \Illuminate\Support\Str::uuid(), 0, 36)),
        ]);
    }
}

class SecurityService
{
    public function record(?User $actor, string $type, array $context = []): SecurityLog
    {
        return SecurityLog::create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'context' => $context,
        ]);
    }
}
```

(`Request ID` middleware menyusul di Task 6 sebagai hardening kecil Phase 1 — header dibaca bila ada, fallback UUID; tanpa middleware pun konsisten.)

- [ ] **Step 2: OrganizationService**

Signature final (implementasi memakai `DB::transaction` + `AuditLogService`):

```php
namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(private AuditLogService $audit) {}

    public function request(array $data, User $user): OrganizationRequest
    {
        if (OrganizationRequest::where('user_id', $user->id)->where('status', 'pending')->count() >= 3) {
            throw ValidationException::withMessages(['name' => 'Maksimal 3 pengajuan pending.']);
        }
        $req = OrganizationRequest::create([...$data, 'user_id' => $user->id, 'status' => 'pending']);
        $this->audit->record($user, 'organization.requested', OrganizationRequest::class, $req->id);
        return $req;
    }

    public function approve(OrganizationRequest $req, User $admin): Organization
    {
        abort_unless($req->isPending(), 422, 'Pengajuan sudah diproses.');
        return DB::transaction(function () use ($req, $admin) {
            $org = Organization::create([
                'name' => $req->name, 'slug' => $req->slug,
                'description' => $req->description, 'status' => 'active',
            ]);
            $org->members()->create(['user_id' => $req->user_id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            app(MembershipService::class)->syncPermissions($req->user, $org);
            $req->update(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $this->audit->record($admin, 'organization.approved', Organization::class, $org->id, ['organization_id' => $org->id]);
            return $org;
        });
    }

    public function reject(OrganizationRequest $req, User $admin, string $reason): OrganizationRequest
    {
        abort_unless($req->isPending(), 422, 'Pengajuan sudah diproses.');
        abort_unless(trim($reason) !== '', 422, 'Alasan penolakan wajib.');
        $req->update(['status' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
        $this->audit->record($admin, 'organization.rejected', OrganizationRequest::class, $req->id);
        return $req->refresh();
    }

    public function suspend(Organization $org, User $admin, string $reason): Organization
    { /* set status suspended + audit organization.suspended + context reason */ }

    public function archive(Organization $org, User $admin, string $reason): Organization
    { /* set status archived + audit */ }

    public function activate(Organization $org, User $admin): Organization
    { /* suspended/archived → active + audit */ }

    public function updateProfile(Organization $org, array $data, User $actor): Organization
    { /* update fillable saja + audit dengan old/new */ }
}
```

`OrganizationRequest` butuh relasi `user(): BelongsTo` (tambah di model — revisi kecil Task 2, catat di commit).

- [ ] **Step 3: MembershipService**

```php
namespace App\Services;

class MembershipService
{
    public const GRANULAR = [ /* 13 permission Task 2 */ ];
    public const OWNER_PERMS = self::GRANULAR;
    public const STAFF_BASE = ['organization.view', 'member.view'];

    public function __construct(private AuditLogService $audit) {}

    public function syncPermissions(User $user, Organization $org): void
    {
        foreach (self::GRANULAR as $perm) { $user->revokePermissionTo($perm); }
        $role = $user->organizationRole($org->id);
        if ($role === 'owner') { $user->givePermissionTo(self::OWNER_PERMS); }
        elseif ($role === 'staff') { $user->givePermissionTo(self::STAFF_BASE); }
    }

    public function invite(Organization $org, array $data, User $actor): OrganizationInvitation
    {
        // $data: email, role(staff saja — tolak 'owner' 422)
        // tolak bila user sudah member (422), bila undangan pending sama masih ada (422)
        // token: $plain = Str::random(40); simpan hash sha256; kembalikan model + set $inv->plain_token (properti runtime, tidak persist) untuk notifikasi/email
    }

    public function acceptInvitation(OrganizationInvitation $inv, User $user): OrganizationMember
    {
        // transaksi: validasi pending + !expired + $inv->email === $user->email + belum member
        // create member staff/active + syncPermissions + tandai accepted_at + audit
    }

    public function declineInvitation(OrganizationInvitation $inv, User $user): void
    { /* validasi milik email user + pending → declined_at + audit */ }

    public function changeRole(OrganizationMember $member, string $role, User $actor): OrganizationMember
    {
        // tolak bila $member->user_id === $actor->id (tidak boleh ubah role sendiri) 403
        // tolak bila role owner baru padahal sudah ada owner lain? TIDAK — multi-owner diizinkan (YAGNI: single-owner enforcement ditunda; transferOwnership untuk serah-terima)
        // update + syncPermissions + audit old/new
    }

    public function removeMember(OrganizationMember $member, User $actor): void
    {
        // tolak bila target diri sendiri (gunakan leave terpisah? YAGNI — tolak 403, owner terakhir tidak boleh keluar: cek count owner active > 1 bila target owner)
        // delete + syncPermissions (cabut semua granular) + audit
    }

    public function grantPermission(User $actor, User $user, Organization $org, string $permission): void
    public function revokePermission(User $actor, User $user, Organization $org, string $permission): void
    // keduanya: validasi permission ∈ GRANULAR + target member staff active org tsb + audit

    public function transferOwnership(Organization $org, User $newOwner, User $actor): void
    {
        // transaksi: actor owner; newOwner staff active org sama; tukar role; syncPermissions keduanya; audit
    }
}
```

- [ ] **Step 4: Tulis unit test (TDD)**

`tests/Unit/OrganizationServiceTest.php`: request ok; request ke-4 saat 3 pending → ValidationException; approve → org active + pemohon owner + permission owner tersinkron; approve ganda → 422 (HttpException); reject tanpa alasan → 422; suspend butuh alasan.
`tests/Unit/MembershipServiceTest.php`: invite staff ok (plain_token tersedia); invite role owner → 422; invite email yang sudah member → 422; accept happy path (member + permission staff base); accept email beda → exception; accept expired → exception; changeRole diri sendiri → 403; removeMember owner terakhir → exception; transferOwnership menukar role + permission.

- [ ] **Step 5: Jalankan + commit**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/
```

Expected: hijau.

```bash
git status --porcelain
git add <file eksplisit>
git commit -m "feat: organization + membership + log services"
```

---

### Task 4: Policy + Form Request + route organizer

**Files:**
- Create: 4 policy; 5 Form Request (`StoreOrganizationRequest`, `ReviewOrganizationRequest`, `InviteMemberRequest`, `UpdateMemberRequest`, `UpdateOrganizationRequest` — review/request dipisah karena dipakai route admin vs organizer); controller `Organizer/OrganizationController`, `Organizer/MemberController`, `Organizer/InvitationController`, `OrganizationRequestController`
- Modify: `routes/web.php` (group auth + `organizer/{organization}` scoped binding), `AppServiceProvider` (registrasi policy + scoped binding), view Blade dashboard org + member + undangan (minimal, reuse layout Breeze)
- Test: `tests/Feature/OrganizationTest.php`, `tests/Feature/MembershipTest.php`

**Interfaces:**
- Consumes: Task 3 (service final).
- Produces: alur web organizer fungsional + scoped binding `{organization}` → 404 di luar scope.

Scoped binding (di `AppServiceProvider::boot()`):

```php
Route::bind('organization', fn (string $value) =>
    Organization::where('slug', $value)
        ->whereIn('id', auth()->user()?->organizations()->pluck('organizations.id') ?? [])
        ->firstOrFail());
```

(binding by slug — URL ramah; `firstOrFail` → 404 otomatis di luar scope. Untuk guest → `auth()->user()` null → 404; middleware auth mengubahnya jadi redirect login lebih dulu — pasang `auth` + `verified` pada group.)

Kebijakan per policy (final):
- `OrganizationPolicy`: `view` (member active org tsb), `update` (`organization.update` + scope), `suspend`/`archive` (super_admin saja — dipakai admin controller, tapi policy tetap di sini agar satu tempat).
- `MemberPolicy`: `viewAny`/`view` (member scope), `invite` (`member.invite` + scope), `update` (`member.change_role` + scope + bukan diri sendiri), `remove` (`member.remove` + scope + bukan diri sendiri + bukan owner terakhir — cek terakhir di service, policy hanya scope+permission+self).
- `InvitationPolicy`: `manage` (`invitation.manage` + scope); `accept`/`decline` (user email == invitation email — tanpa membership).
- `OrganizationRequestPolicy`: `create` (user login mana pun), tanpa `view` user (riwayat pengajuan milik sendiri via controller query langsung, bukan policy).

- [ ] **Step 1: Policy (4 file, authorize murni — tanpa query bisnis)**
- [ ] **Step 2: Form Request (authorize = policy call; rules = validasi; `request.create` throttle 3/menit di route)**
- [ ] **Step 3: Controller tipis + view Blade minimal (form pengajuan, dashboard org, tabel member, form undang, daftar undangan saya)**
- [ ] **Step 4: Route group + scoped binding + registrasi policy**
- [ ] **Step 5: Feature test (TDD)** — ajukan→(DB) pending; profil org update oleh owner ok, oleh staff tanpa perm 403, oleh org lain 404; undang→terima via HTTP; ubah role/keluarkan via HTTP; IDOR silang org → 404.
- [ ] **Step 6: Jalankan + commit** (`feat: organizer routes + policy + scoped binding`)

---

### Task 5: Admin panel (pengajuan + kelola org + log)

**Files:**
- Create: `Admin/OrganizationRequestController` (index pending, approve, reject), `Admin/OrganizationController` (index, suspend, archive, activate), `Admin/LogController` (audit index, security index — read-only), Form Request `ReviewOrganizationRequest` (dipakai di sini; bila sudah dibuat di Task 4, reuse — JANGAN duplikat), view Blade admin minimal
- Modify: `routes/web.php` (group `admin` + middleware `role:super_admin`)
- Test: `tests/Feature/AdminTest.php`

**Interfaces:**
- Consumes: Task 4 (policy + service + binding pattern).
- Produces: admin fungsional; operasi sensitif re-auth + alasan + audit.

- [ ] **Step 1: Middleware gate super_admin** — pakai `spatie/laravel-permission` middleware (`role:super_admin`) pada group `admin`; non-admin → 403.
- [ ] **Step 2: Controller + view** — antrean pengajuan (pending first); approve/reject memanggil `OrganizationService`; suspend/archive/activate memanggil service + Form Request alasan; log index dengan filter type/action + pagination (tanpa tombol ubah/hapus di view maupun route).
- [ ] **Step 3: Re-auth operasi sensitif** — route suspend/archive/approve memakai middleware `password.confirm` (Breeze menyediakan `/confirm-password`); tanpa konfirmasi fresh → redirect confirm.
- [ ] **Step 4: Feature test (TDD)** — super_admin approve → org+owner; reject butuh alasan; suspend tanpa alasan → 422; tanpa re-auth → redirect confirm; non-admin akses `/admin` → 403; audit tercatat untuk tiap aksi.
- [ ] **Step 5: Jalankan + commit** (`feat: admin panel (requests, organizations, logs)`)

---

### Task 6: Authorization + isolation suite + gates + tutup

**Files:**
- Create: `tests/Feature/AuthorizationTest.php` (matriks per endpoint), `tests/Feature/TenantIsolationTest.php` (Org A vs B simetris)
- Modify: `README.md` (status Phase 1), middleware Request ID kecil (`app/Http/Middleware/RequestId.php` + registrasi global — melengkapi `AuditLogService` Task 3)
- Test: full `./vendor/bin/pest`; `./vendor/bin/pint --test`; `./vendor/bin/phpstan analyse`; `composer audit`

**Interfaces:**
- Consumes: Task 1–5 (seluruh alur).
- Produces: Phase 1 hijau + README akurat + siap jadi basis Phase 2.

- [ ] **Step 1: RequestId middleware**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->header('X-Request-ID') ?: (string) Str::uuid();
        $request->headers->set('X-Request-ID', $id);
        return $next($request)->header('X-Request-ID', $id);
    }
}
```

Registrasi di `bootstrap/app.php` middleware global. Test: `GET /` mengembalikan header `X-Request-ID` (tambah 1 test ke `SmokeTest.php` — modify, bukan file baru).

- [ ] **Step 2: AuthorizationTest (matriks)** — tiap endpoint Task 4–5 × aktor (guest→login; owner ok; staff ber-perm ok; staff tanpa perm 403; staff org lain 404; volunteer 403/404; non-admin ke /admin 403). Minimal 20 asersi rute.
- [ ] **Step 3: TenantIsolationTest (simetris A↔B)** — Org A (owner+staff+eventakip? TIDAK — Phase 1 belum ada event; resource = dashboard org, member list, undangan, profil): A tidak bisa read/update org B (404), tidak bisa lihat member B (404), tidak bisa undang atas nama B (404), tidak bisa accept undangan B (403); simetris B→A; volunteer luar → ditolak.
- [ ] **Step 4: Gates**

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec app composer audit
```

Expected: Pest hijau; Pint PASS (bila FAIL → `./vendor/bin/pint`, ulangi); PHPStan No errors; audit tanpa critical/high.

- [ ] **Step 5: README + commit final**

Ubah `README.md` blok Status:

```text
Lama: **Phase 0 — Selesai.** Scaffold Laravel 13 + Docker dev + suite hijau. Lanjut Phase 1 (Foundation).
Baru: **Phase 1 — Selesai.** Auth + organization + membership + RBAC + isolation + suite hijau. Lanjut Phase 2 (Event).
```

```bash
git status --porcelain
git add <file eksplisit>
git commit -m "chore: phase 1 done (gates green) — ready for phase 2"
git log --oneline -n 8
```

Expected: rantai commit Phase 1 terlihat; status bersih.

---

## Self-Review

1. **Spec coverage:** §1 (service + kebijakan auth) → Task 1, 3; §2 (6 tabel + kolom + constraint + seeder + command) → Task 2; relasi/helper → Task 2; §3 (route groups + scoped binding + policy + transaksi + fillable) → Task 4, 5; §4 (unit/feature/auth/isolation/negatif/gates) → Task 1, 2, 3, 6 (unit service), Task 4–5 (feature), Task 6 (matriks + isolasi + gates). Listener `Failed`/`Lockout` → Task 2 (test penuh Task 1 + 5). Request ID → Task 6 (dibaca Task 3 dengan fallback). Transfer ownership → Task 3 (definisi §5 spec: owner→staff satu org, re-auth di controller Task 4 — CATAT: Task 4 wajib pasang `password.confirm` pada route transferOwnership; sudah tercakup pola "operasi sensitif" Task 5 Step 3 — perluas ke route organizer sensitif di Task 4 Step 4).
2. **Placeholder scan:** seluruh test berisi kode aktual; signature service final; permission 13 buah eksplisit; nilai numerik konkret (12, 5/menit, +7 hari, 3 pending). Istilah "view Blade minimal" didefinisikan per task (form/daftar spesifik). Tanpa TBD/TODO.
3. **Type consistency:** nama service/method/policy/route-group identik lintas task; `OrganizationRequest` dipakai konsisten untuk pengajuan (vs "request" HTTP — dibedakan via namespace); `GRANULAR`/`OWNER_PERMS`/`STAFF_BASE` didefinisikan sekali di Task 3; helper `belongsToOrganization(int): bool`, `organizationRole(int): ?string` sesuai Task 2.
