# Phase 5D Talent Pool & Volunteer Invitation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul Talent Pool terisolasi per organisasi untuk mencari relawan berpengalaman dari riwayat event sebelumnya (berdasarkan skill, lokasi, riwayat role, persentase kehadiran, dan sertifikat) serta mengirimkan dan mengelola Undangan Event.

**Architecture:** Modul terisolasi per `Organization` dengan `TalentPoolService` untuk pencarian & agregasi data relawan, `InvitationService` untuk pengelolaan siklus undangan (`EventInvitation`) beserta pengikatan atomik kuota via `QuotaService`, `TalentPolicy` untuk otorisasi RBAC Spatie, controller tipis via Form Request, serta view Blade untuk organizer dan portal relawan.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, Spatie Permission, Pest, Blade.

**Spec:** `docs/superpowers/specs/2026-09-23-phase5d-talent-pool-design.md`

## Global Constraints

- Docker via `docker compose exec app` untuk semua perintah artisan/composer/pest.
- Full suite via biner langsung dengan memory 1G: `php -d memory_limit=1G ./vendor/bin/pest`.
- Quality gates per task: Pest hijau, Pint (`./vendor/bin/pint --test`), Larastan level 5 (`./vendor/bin/phpstan analyse --no-progress`), `composer audit` bersih di akhir.
- Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model; business logic hanya di service.
- `$fillable` allowlist kosong; tulis kolom via `Model::unguarded()` / `forceFill()` di service.
- Check constraints via `DB::statement` (pola 5B/5C, bukan enum native).
- Throttle store/respond 30/menit.
- Paginasi index 15; flash pesan berbahasa Indonesia.

---

## File Map

**Buat Baru:**
- `database/migrations/2026_09_23_000001_create_event_invitations_table.php` — tabel `event_invitations` + check constraint status + partial unique index pending.
- `database/migrations/2026_09_23_000002_backfill_talent_permissions.php` — backfill permission `talent.search` & `talent.invite` untuk pemilik organisasi aktif.
- `app/Models/EventInvitation.php` — Model dengan `STATUSES`, relasi `event()`, `user()`, `role()`, `invitedBy()`.
- `app/Services/TalentPoolService.php` — Pencarian & agregasi portofolio relawan per tenant.
- `app/Services/InvitationService.php` — Satu-satunya penulis tabel `event_invitations`.
- `app/Policies/TalentPolicy.php` — Otorisasi `search`, `view`, `invite`.
- `app/Http/Requests/SearchTalentRequest.php`, `app/Http/Requests/StoreInvitationRequest.php`, `app/Http/Requests/RespondInvitationRequest.php`.
- `app/Http/Controllers/OrganizerTalentController.php` — `index`, `show`, `invite`, `cancel`.
- `app/Http/Controllers/VolunteerInvitationController.php` — `index`, `respond`.
- Blade Views:
  - `resources/views/organizer/talent/index.blade.php`
  - `resources/views/organizer/talent/show.blade.php`
  - `resources/views/my/invitations/index.blade.php`
- Tests:
  - `tests/Feature/TalentPoolSearchTest.php`
  - `tests/Feature/EventInvitationTest.php`
  - `tests/Feature/TalentAuthorizationTest.php`
  - `tests/Feature/BackfillTalentPermissionsTest.php`

**Ubah:**
- `database/seeders/PermissionSeeder.php` — Tambah `talent.search` & `talent.invite`.
- `app/Models/User.php` — Tambah relasi `eventInvitations(): HasMany`.
- `app/Models/Event.php` — Tambah relasi `invitations(): HasMany`.
- `app/Services/MembershipService.php` — Sinkronisasi permission owner baru untuk `talent.search` & `talent.invite`.
- `routes/web.php` — Group `organizer/{organization:slug}/talent` & route `my/invitations`.

---

### Task 1: Migrasi, Model & Permission Talent

**Files:**
- Create: `database/migrations/2026_09_23_000001_create_event_invitations_table.php`
- Create: `app/Models/EventInvitation.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Event.php`

- [ ] **Step 1: Tulis migrasi tabel `event_invitations`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('event_roles')->nullOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::statement("ALTER TABLE event_invitations ADD CONSTRAINT event_invitations_status_check CHECK (status IN ('pending','accepted','declined','expired','cancelled'))");
        DB::statement("CREATE UNIQUE INDEX event_invitations_pending_unique ON event_invitations (event_id, user_id) WHERE status = 'pending' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
    }
};
```

- [ ] **Step 2: Tulis model `EventInvitation`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInvitation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'accepted', 'declined', 'expired', 'cancelled'];

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EventRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EventRole::class, 'role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
```

- [ ] **Step 3: Tambahkan permission ke `PermissionSeeder.php` & relasi Model**
  - Tambahkan `'talent.search'`, `'talent.invite'` ke `PermissionSeeder.php`.
  - Tambahkan `eventInvitations(): HasMany` pada `User.php`.
  - Tambahkan `invitations(): HasMany` pada `Event.php`.

- [ ] **Step 4: Jalankan migrasi dan seeder di Docker**
  - `docker compose exec app php artisan migrate --force`
  - `docker compose exec app php artisan db:seed --class=PermissionSeeder --force`

- [ ] **Step 5: Commit Task 1**
  - `git add database/migrations/2026_09_23_000001_create_event_invitations_table.php app/Models/EventInvitation.php database/seeders/PermissionSeeder.php app/Models/User.php app/Models/Event.php`
  - `git commit -m "feat: migrasi dan model event invitations"`

---

### Task 2: Service Layer (`TalentPoolService` & `InvitationService`)

**Files:**
- Create: `app/Services/TalentPoolService.php`
- Create: `app/Services/InvitationService.php`
- Test: `tests/Feature/TalentPoolSearchTest.php`
- Test: `tests/Feature/EventInvitationTest.php`

- [ ] **Step 1: Tulis unit test gagal `TalentPoolSearchTest.php`**

```php
it('organizer dapat mencari relawan dari riwayat event miliknya', function () {
    // setup org, event, volunteer accepted
    // assert search returns volunteer
});

it('relawan dengan visibility private disembunyikan dari talent pool', function () {
    // setup private volunteer
    // assert search excludes volunteer
});

it('organizer lain tidak dapat menemukan relawan yang tidak pernah mendaftar di org-nya', function () {
    // setup tenant isolation
    // assert search returns empty for Org B
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**
  - `docker compose exec app php artisan test --filter=TalentPoolSearchTest`

- [ ] **Step 3: Tulis `TalentPoolService.php`**

```php
<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TalentPoolService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{q?: ?string, skills?: ?string, city?: ?string, min_attendance?: ?int, page?: ?int} $filters */
    public function search(Organization $organization, User $actor, array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('registrations', function ($q) use ($organization) {
                $q->whereHas('event', function ($eq) use ($organization) {
                    $eq->where('organization_id', $organization->id);
                })->whereIn('status', ['accepted', 'completed']);
            })
            ->whereHas('volunteerProfile', function ($pq) {
                $pq->where('visibility', '!=', 'private');
            })
            ->with(['volunteerProfile']);

        if (!empty($filters['q'])) {
            $keyword = '%' . trim($filters['q']) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'ILIKE', $keyword)
                  ->orWhere('email', 'ILIKE', $keyword);
            });
        }

        if (!empty($filters['city'])) {
            $city = '%' . trim($filters['city']) . '%';
            $query->whereHas('volunteerProfile', function ($pq) use ($city) {
                $pq->where('city', 'ILIKE', $city);
            });
        }

        if (!empty($filters['skills'])) {
            $skill = '%' . trim($filters['skills']) . '%';
            $query->whereHas('volunteerProfile', function ($pq) use ($skill) {
                $pq->where('skills', 'ILIKE', $skill);
            });
        }

        $results = $query->paginate(15);

        $this->audit->record($actor, 'talent.searched', Organization::class, $organization->id, [
            'filters' => $filters,
        ]);

        return $results;
    }
}
```

- [ ] **Step 4: Tulis `InvitationService.php`**

```php
<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventRole;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvitationService
{
    public function __construct(
        private AuditLogService $audit,
        private QuotaService $quota
    ) {}

    public function invite(Event $event, User $actor, User $volunteer, ?int $roleId = null, ?string $message = null): EventInvitation
    {
        abort_if(in_array($event->status, ['archived', 'cancelled'], true), 422, 'Event sudah tidak aktif.');

        $isRegistered = Registration::where('event_id', $event->id)
            ->where('user_id', $volunteer->id)
            ->whereIn('status', ['pending', 'under_review', 'accepted', 'waitlisted'])
            ->exists();

        abort_if($isRegistered, 422, 'Relawan sudah terdaftar pada event ini.');

        $pendingExist = EventInvitation::where('event_id', $event->id)
            ->where('user_id', $volunteer->id)
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->exists();

        abort_if($pendingExist, 422, 'Undangan pending untuk relawan ini sudah ada.');

        return DB::transaction(function () use ($event, $actor, $volunteer, $roleId, $message): EventInvitation {
            $invitation = EventInvitation::unguarded(fn () => EventInvitation::create([
                'event_id' => $event->id,
                'user_id' => $volunteer->id,
                'role_id' => $roleId,
                'invited_by_id' => $actor->id,
                'message' => $message,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]));

            $this->audit->record($actor, 'talent.invited', Event::class, $event->id, [
                'invitation_id' => $invitation->id,
                'volunteer_id' => $volunteer->id,
            ]);

            return $invitation;
        });
    }

    public function respond(EventInvitation $invitation, User $volunteer, string $action): EventInvitation
    {
        abort_unless($invitation->user_id === $volunteer->id, 403, 'Otorisasi tidak sah.');
        abort_unless($invitation->status === 'pending', 422, 'Undangan sudah direspon atau dibatalkan.');
        abort_if($invitation->isExpired(), 422, 'Undangan sudah kadaluarsa.');
        abort_unless(in_array($action, ['accepted', 'declined'], true), 422, 'Aksi undangan tidak sah.');

        return DB::transaction(function () use ($invitation, $volunteer, $action): EventInvitation {
            if ($action === 'accepted') {
                $roleId = $invitation->role_id;
                if ($roleId !== null) {
                    $role = EventRole::findOrFail($roleId);
                    $this->quota->claimSlot($role);
                }

                Registration::unguarded(fn () => Registration::create([
                    'event_id' => $invitation->event_id,
                    'user_id' => $volunteer->id,
                    'role_id' => $invitation->role_id,
                    'status' => 'accepted',
                ]));
            }

            $invitation->forceFill([
                'status' => $action,
                'responded_at' => now(),
            ])->save();

            $this->audit->record($volunteer, 'talent_invitation.' . $action, EventInvitation::class, $invitation->id, []);

            return $invitation;
        });
    }

    public function cancel(EventInvitation $invitation, User $actor): EventInvitation
    {
        abort_unless($invitation->status === 'pending', 422, 'Hanya undangan pending yang dapat dibatalkan.');

        return DB::transaction(function () use ($invitation, $actor): EventInvitation {
            $invitation->forceFill(['status' => 'cancelled'])->save();

            $this->audit->record($actor, 'talent_invitation.cancelled', EventInvitation::class, $invitation->id, []);

            return $invitation;
        });
    }
}
```

- [ ] **Step 5: Run tests, pastikan hijau**
  - `docker compose exec app php artisan test --filter='TalentPoolSearchTest|EventInvitationTest'`

- [ ] **Step 6: Commit Task 2**
  - `git add app/Services/TalentPoolService.php app/Services/InvitationService.php tests/Feature/TalentPoolSearchTest.php tests/Feature/EventInvitationTest.php`
  - `git commit -m "feat: talent pool service dan invitation service"`

---

### Task 3: Policy, Form Requests, Controller & Route

**Files:**
- Create: `app/Policies/TalentPolicy.php`
- Create: `app/Http/Requests/SearchTalentRequest.php`
- Create: `app/Http/Requests/StoreInvitationRequest.php`
- Create: `app/Http/Requests/RespondInvitationRequest.php`
- Create: `app/Http/Controllers/OrganizerTalentController.php`
- Create: `app/Http/Controllers/VolunteerInvitationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TalentAuthorizationTest.php`

- [ ] **Step 1: Tulis `TalentPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class TalentPolicy
{
    public function search(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id)
            && $user->can('talent.search');
    }

    public function invite(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id)
            && $user->can('talent.invite');
    }
}
```

- [ ] **Step 2: Tulis Form Requests**
  - `SearchTalentRequest`: `authorize()` validate member + `talent.search`. `rules()` validate `q`, `city`, `skills` nullable string max 100.
  - `StoreInvitationRequest`: `user_id` required exists users, `role_id` nullable exists event_roles, `message` nullable string max 1000.
  - `RespondInvitationRequest`: `action` required in `accepted,declined`.

- [ ] **Step 3: Tulis Controllers**
  - `OrganizerTalentController` (`index`, `show`, `invite`, `cancel`).
  - `VolunteerInvitationController` (`index`, `respond`).

- [ ] **Step 4: Tambahkan rute ke `routes/web.php`**

```php
Route::prefix('organizer/{organization:slug}')->name('organizer.')->group(function () {
    Route::get('talent', [OrganizerTalentController::class, 'index'])->name('talent.index');
    Route::get('talent/{user}', [OrganizerTalentController::class, 'show'])->name('talent.show');
    Route::post('events/{event}/invitations', [OrganizerTalentController::class, 'invite'])
        ->middleware('throttle:30,1')->name('talent.invite');
    Route::delete('events/{event}/invitations/{invitation}', [OrganizerTalentController::class, 'cancel'])
        ->name('talent.invitation.cancel');
});

Route::middleware(['auth'])->group(function () {
    Route::get('my/invitations', [VolunteerInvitationController::class, 'index'])->name('my.invitations.index');
    Route::post('my/invitations/{invitation}/respond', [VolunteerInvitationController::class, 'respond'])
        ->middleware('throttle:30,1')->name('my.invitations.respond');
});
```

- [ ] **Step 5: Run tests otorisasi**
  - `docker compose exec app php artisan test --filter=TalentAuthorizationTest`

- [ ] **Step 6: Commit Task 3**
  - `git add app/Policies/TalentPolicy.php app/Http/Requests/ app/Http/Controllers/ routes/web.php tests/Feature/TalentAuthorizationTest.php`
  - `git commit -m "feat: controller request route otorisasi talent pool"`

---

### Task 4: Blade Views (Organizer & Relawan)

**Files:**
- Create: `resources/views/organizer/talent/index.blade.php`
- Create: `resources/views/organizer/talent/show.blade.php`
- Create: `resources/views/my/invitations/index.blade.php`

- [ ] **Step 1: Buat tampilan `organizer/talent/index.blade.php`**
  - Form pencarian kata kunci, kota, dan skill.
  - Tabel/grid daftar relawan dengan badge skill & tombol modal *"Undang ke Event"*.

- [ ] **Step 2: Buat tampilan `organizer/talent/show.blade.php`**
  - Portofolio internal relawan (riwayat event, role, kehadiran, sertifikat).

- [ ] **Step 3: Buat tampilan `my/invitations/index.blade.php`**
  - Daftar undangan event masuk untuk relawan beserta tombol *Terima* / *Tolak*.

- [ ] **Step 4: Commit Task 4**
  - `git add resources/views/organizer/talent/ resources/views/my/invitations/`
  - `git commit -m "feat: blade view talent pool organizer dan undangan relawan"`

---

### Task 5: Backfill Permission & Quality Gates

**Files:**
- Create: `database/migrations/2026_09_23_000002_backfill_talent_permissions.php`
- Create: `tests/Feature/BackfillTalentPermissionsTest.php`

- [ ] **Step 1: Tulis migrasi backfill & test-nya**
  - Memberikan `talent.search` dan `talent.invite` pada owner organisasi aktif.

- [ ] **Step 2: Jalankan full Pest test suite**
  - `docker compose exec app php -d memory_limit=1G ./vendor/bin/pest`

- [ ] **Step 3: Pint formatting & Larastan check**
  - `docker compose exec app ./vendor/bin/pint`
  - `docker compose exec app ./vendor/bin/phpstan analyse --no-progress`

- [ ] **Step 4: Composer security audit**
  - `docker compose exec app composer audit`

- [ ] **Step 5: Commit Task 5**
  - `git add database/migrations/2026_09_23_000002_backfill_talent_permissions.php tests/Feature/BackfillTalentPermissionsTest.php`
  - `git commit -m "feat: backfill permission talent dan quality gates"`

---

## Self-Review

1. **Spec Coverage:**
   - Pencarian & isolasi tenant $\rightarrow$ Task 2 & 3.
   - Privasi & data minimization $\rightarrow$ Task 2 (`visibility != private`).
   - Undangan event & transaksi atomik kuota $\rightarrow$ Task 2 (`InvitationService`).
   - Permission & backfill $\rightarrow$ Task 1 & 5.
   - Blade views & route $\rightarrow$ Task 3 & 4.
2. **Placeholder Scan:** Bebas dari TBD/TODO.
3. **Type Consistency:** Signature `TalentPoolService::search` dan `InvitationService::invite/respond/cancel` konsisten di seluruh tugas.
