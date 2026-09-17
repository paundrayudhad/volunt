# Phase 2 (Event) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Organizer mengelola event + division/role/shift dalam tenant-nya dengan state machine tervalidasi, dan publik bisa browse katalog event + search/filter.

**Architecture:** Modul domain `Events` menempel rantai tenancy Phase 1 (`User → Organization → Event → Division → Role → Shift`). Satu service baru (`EventService`, satu-satunya penulis `status` event); 4 policy; scoped binding ganda (org → event → sub-resource); audit via `AuditLogService` Phase 1 (kolom `event_id` akhirnya terisi).

**Tech Stack:** Laravel 13, PHP 8.4 (container `app` via `docker compose exec app`), PostgreSQL 16, Blade + Livewire 4.4 (pola Breeze Phase 1), Spatie Permission, Pest 4.7, Pint, Larastan level 5, Tailwind v4.

**Spec:** `docs/superpowers/specs/2026-09-17-phase2-event-design.md` (+ PRD §5–6, DATABASE.md §Events, SECURITY.md §5, TESTING.md di repo root).

## Global Constraints

- Prose (laporan, komentar, pesan commit, teks Blade) Bahasa Indonesia; kode/identifier/branch/commit English.
- Setiap step konten nyata — tanpa TBD/TODO/placeholder.
- `git add` file eksplisit setelah `git status --porcelain`; NEVER `git add -A`.
- Commit diakhiri `Co-Authored-By: Claude Code <noreply@anthropic.com>`.
- Kerja in place (tanpa worktree); PHP/composer/artisan via `docker compose exec app ...`; Node/npm di host.
- Postgres 16 service `db` (DB `webvolunteer` / `webvolunteer_test`); tanpa penambahan Redis/MinIO/service.
- Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. `organization_id`/`event_id`/`status` tidak pernah fillable — di-set server-side.
- Permission = kemampuan AND membership = cakupan (`can()` + `belongsToOrganization()`); owner dapat semua perm event via `syncPermissions`.
- Audit append-only via `AuditLogService`; controller tidak pernah menulis log langsung.

---

### Task 1: Migrasi + model + permission event

**Files:**
- Create: `database/migrations/2026_09_17_000001_create_events_table.php`, `..._000002_create_event_divisions_table.php`, `..._000003_create_event_roles_table.php`, `..._000004_create_event_shifts_table.php`
- Create: `app/Models/Event.php`, `app/Models/EventDivision.php`, `app/Models/EventRole.php`, `app/Models/EventShift.php`
- Modify: `app/Models/Organization.php` (tambah relasi `events()`), `app/Models/User.php` (relasi supervisi tidak perlu — supervisor diakses via FK langsung), `database/seeders/PermissionSeeder.php` (tambah 8 permission), `app/Services/MembershipService.php` (`GRANULAR` +8), `database/factories/EventFactory.php` (+ factory division/role/shift seperlunya untuk test)
- Test: `tests/Unit/EventModelRelationTest.php`

**Interfaces:**
- Consumes: `Organization` (Phase 1), `PermissionSeeder::PERMISSIONS`, `MembershipService::GRANULAR/OWNER_PERMS/STAFF_BASE`.
- Produces: `Event`, `EventDivision`, `EventRole`, `EventShift` + relasi + scope (`forOrganization()`, `forEvent()`, `active()`, `published()`) untuk Task 2–6; 8 permission baru di seeder + GRANULAR.

- [ ] **Step 1: Tulis test relasi model (failing)**

```php
it('event milik organisasi dengan relasi division role shift', function (): void {
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org)->create();
    $div = EventDivision::factory()->for($event)->create();
    $role = EventRole::factory()->for($event)->for($div, 'division')->create();
    $shift = EventShift::factory()->for($event)->for($div, 'division')->for($role, 'role')->create();

    expect($event->organization->is($org))->toBeTrue()
        ->and($event->divisions)->toHaveCount(1)
        ->and($role->shifts)->toHaveCount(1)
        ->and($org->events->first()->is($event))->toBeTrue();
});

it('slug unik per organisasi tetapi boleh sama lintas organisasi', function (): void {
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();
    Event::factory()->for($a)->create(['slug' => 'festival']);
    Event::factory()->for($b)->create(['slug' => 'festival']);

    expect(fn () => Event::factory()->for($a)->create(['slug' => 'festival']))
        ->toThrow(QueryException::class);
});

it('check constraint menolak end_at sebelum start_at dan accepted_count melebihi quota', function (): void {
    $event = Event::factory()->create();
    $div = EventDivision::factory()->for($event)->create();

    expect(fn () => Event::factory()->create([
        'start_at' => now()->addDay(), 'end_at' => now(),
    ]))->toThrow(QueryException::class);

    $role = EventRole::factory()->for($event)->for($div, 'division')->create(['quota' => 2]);
    expect(fn () => $role->forceFill(['accepted_count' => 3])->save())
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `docker compose exec app ./vendor/bin/pest tests/Unit/EventModelRelationTest.php`
Expected: FAIL (`Class "App\Models\Event" not found`).

- [ ] **Step 3: Tulis 4 migrasi**

`2026_09_17_000001_create_events_table.php`:

```php
Schema::create('events', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->string('category')->nullable();
    $table->string('venue')->nullable();
    $table->string('address')->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('timezone')->default('Asia/Jakarta');
    $table->timestampTz('start_at');
    $table->timestampTz('end_at');
    $table->timestampTz('registration_start_at')->nullable();
    $table->timestampTz('registration_end_at')->nullable();
    $table->string('status')->default('draft');
    $table->integer('capacity')->nullable();
    $table->jsonb('contact')->nullable();
    $table->jsonb('branding')->nullable();
    $table->string('banner_path')->nullable();
    $table->string('thumbnail_path')->nullable();
    $table->text('terms')->nullable();
    $table->text('privacy_notice')->nullable();
    $table->timestampTz('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['organization_id', 'slug']);
    $table->index(['organization_id', 'status']);
    $table->index(['status', 'start_at']);
    $table->check("status IN ('draft','published','registration_open','registration_closed','ongoing','completed','archived','cancelled')");
    $table->check('end_at > start_at');
    $table->check('registration_start_at IS NULL OR registration_end_at IS NULL OR registration_start_at < registration_end_at');
});
```

`000002_event_divisions`: `event_id` FK cascade, `name`, `description` nullable, `supervisor_id` FK users nullable nullOnDelete, `status` default `active`, timestamps; unique `(event_id, name)`; check status `IN ('active','archived')`.

`000003_event_roles`: `event_id` + `division_id` FK cascade, `name`, `description` nullable, `quota` default 0, `accepted_count` default 0, `requirements` jsonb nullable, `location` nullable, `status` default `active`, timestamps; unique `(event_id, division_id, name)`; check `quota >= 0`, `accepted_count >= 0`, `accepted_count <= quota`.

`000004_event_shifts`: `event_id` + `division_id` FK cascade, `role_id` FK cascade nullable, `start_at`/`end_at` timestamptz, `location` nullable, `capacity` nullable, `supervisor_id` FK users nullable nullOnDelete, `status` default `active`, timestamps; index `(event_id, start_at)`, `(role_id, start_at)`; check `end_at > start_at`, `capacity IS NULL OR capacity >= 0`.

Catatan: bila `$table->check()` tidak didukung Laravel 13, gunakan `DB::statement('ALTER TABLE ... ADD CONSTRAINT ...')` — hasil akhir diuji via QueryException, bukan mekanisme.

- [ ] **Step 4: Tulis 4 model + relasi Organization + factory**

`Event`: `HasFactory, SoftDeletes`; fillable `name, slug, description, category, venue, address, latitude, longitude, timezone, start_at, end_at, registration_start_at, registration_end_at, capacity, contact, branding, banner_path, thumbnail_path, terms, privacy_notice` (TANPA `organization_id`, `status`, `published_at`); casts `contact/branding => array`, `start_at/end_at/registration_* => datetime`; relasi `organization(): BelongsTo`, `divisions()/roles()/shifts(): HasMany`; scope `forOrganization($orgId)`, `forEvent` (n/a — event adalah root), `scopeActive` (`status NOT IN ('cancelled','archived')`), `scopePublished` (`status IN ('published','registration_open','registration_closed','ongoing')`); helper `isTerminal(): bool` (`archived`/`cancelled`), `isPubliclyVisible(): bool`.

`EventDivision`: fillable `name, description` (+ `supervisor_id`? TIDAK — di-set server-side via `forceFill` agar konsisten pola "FK sensitif server-side"); relasi `event()`, `roles()`, `shifts()`, `supervisor(): BelongsTo(User)`; helper `isArchived()`.

`EventRole`: fillable `name, description, quota, requirements, location`; relasi `event()`, `division()`, `shifts()`; helper `remainingQuota(): int` (`max(0, quota - accepted_count)`).

`EventShift`: fillable `start_at, end_at, location, capacity`; relasi `event()`, `division()`, `role()`, `supervisor()`; helper `isPast(): bool`.

`Organization`: tambah `events(): HasMany`. Factory `EventFactory` (nama, slug unik-per-org via sequence, start/end valid, status `draft`) + factory division/role/shift minimal untuk test.

- [ ] **Step 5: Tambah 8 permission ke seeder + GRANULAR**

`PermissionSeeder::PERMISSIONS` tambah: `event.create`, `event.view`, `event.update`, `event.delete`, `event.publish`, `division.manage`, `role.manage`, `shift.manage`. `MembershipService::GRANULAR` tambah 8 yang sama (posisi setelah `security.read`). `OWNER_PERMS` otomatis ikut (alias GRANULAR). `STAFF_BASE` tambah `event.view` (staff bisa lihat event org-nya; konsisten `organization.view` di base).

- [ ] **Step 6: Jalankan migrasi kedua DB + test**

Run: `docker compose exec app php artisan migrate && docker compose exec app php artisan migrate --env=testing` (atau pola Phase 1 untuk DB test), lalu `docker compose exec app ./vendor/bin/pest tests/Unit/EventModelRelationTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git status --porcelain
git add database/migrations/2026_09_17_00000*.php app/Models/Event.php app/Models/EventDivision.php app/Models/EventRole.php app/Models/EventShift.php app/Models/Organization.php database/seeders/PermissionSeeder.php app/Services/MembershipService.php database/factories/EventFactory.php database/factories/EventDivisionFactory.php database/factories/EventRoleFactory.php database/factories/EventShiftFactory.php tests/Unit/EventModelRelationTest.php
git commit -m "feat: event schema + models + permissions"
```

---

### Task 2: EventService (CRUD + state machine)

**Files:**
- Create: `app/Services/EventService.php`
- Test: `tests/Unit/EventServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models; `AuditLogService::record()` (Phase 1); `MembershipService::syncPermissions` (tidak perlu di sini — permission event ikut sync org yang sudah ada).
- Produces: `EventService::{createEvent, updateEvent, deleteEvent, transitionTo, createDivision, updateDivision, deleteDivision, createRole, updateRole, deleteRole, createShift, updateShift, deleteShift}` untuk Task 3–5.

- [ ] **Step 1: Tulis failing test (transisi + CRUD + guard)**

```php
it('create event dalam org aktif menghasilkan draft + audit', function (): void {
    $owner = orgTesOwner(); // helper: user + org aktif + role owner (lihat pola Task 3 Phase 1)
    $event = app(EventService::class)->createEvent($owner->organizations()->first(), [
        'name' => 'Festival', 'slug' => 'festival',
        'start_at' => now()->addMonth(), 'end_at' => now()->addMonth()->addDays(2),
    ], $owner);

    expect($event->status)->toBe('draft')
        ->and($event->organization_id)->toBe($owner->organizations()->first()->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'event.created']);
});

it('transition valid draft sampai archived lolos berurutan', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    foreach (['published','registration_open','registration_closed','ongoing','completed','archived'] as $next) {
        $event = $svc->transitionTo($event, $next, $owner);
        expect($event->status)->toBe($next);
    }
});

it('transition invalid dan cancel tanpa alasan ditolak', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);

    expect(fn () => $svc->transitionTo($event, 'completed', $owner))
        ->toThrow(HttpException::class); // draft→completed invalid → 422
    expect(fn () => $svc->transitionTo($event, 'cancelled', $owner))
        ->toThrow(HttpException::class); // tanpa reason → 422
    $event = $svc->transitionTo($event, 'cancelled', $owner, 'Sponsor mundur');
    expect($event->status)->toBe('cancelled');
    expect(fn () => $svc->transitionTo($event, 'draft', $owner))
        ->toThrow(HttpException::class); // terminal tak bisa keluar
});

it('cancelled dan archived menolak edit dan operasi division', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    $svc->transitionTo($event, 'cancelled', $owner, 'Bencana alam');

    expect(fn () => $svc->updateEvent($event->fresh(), ['name' => 'Baru'], $owner))
        ->toThrow(HttpException::class);
    expect(fn () => $svc->createDivision($event->fresh(), ['name' => 'Stage'], $owner))
        ->toThrow(HttpException::class);
});

it('crud division role shift ter-scope event + sisa kuota', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    $div = $svc->createDivision($event, ['name' => 'Stage'], $owner);
    $role = $svc->createRole($event, $div, ['name' => 'Usher', 'quota' => 5], $owner);
    $shift = $svc->createShift($event, $div, $role, [
        'start_at' => $event->start_at, 'end_at' => $event->end_at,
    ], $owner);

    expect($role->remainingQuota())->toBe(5)
        ->and($shift->event_id)->toBe($event->id);
    $svc->deleteShift($shift, $owner);
    expect(EventShift::find($shift->id))->toBeNull();
});
```

Helper `eventTesSetup(): array` didefinisikan di file test ini (buat user + org aktif + owner + event draft via service; unik prefix `eventTes` agar tak bentrok helper Phase 1).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker compose exec app ./vendor/bin/pest tests/Unit/EventServiceTest.php`
Expected: FAIL (`Class "App\Services\EventService" not found`).

- [ ] **Step 3: Implementasi EventService**

```php
class EventService
{
    public const TRANSITIONS = [
        'draft' => ['published', 'cancelled'],
        'published' => ['registration_open', 'cancelled'],
        'registration_open' => ['registration_closed', 'cancelled'],
        'registration_closed' => ['ongoing', 'cancelled'],
        'ongoing' => ['completed', 'cancelled'],
        'completed' => ['archived'],
        'archived' => [],
        'cancelled' => [],
    ];

    public function __construct(private AuditLogService $audit) {}

    public function createEvent(Organization $org, array $data, User $actor): Event
    {
        abort_unless($org->status === 'active', 422, 'Organisasi tidak aktif.');
        $event = DB::transaction(fn () => Event::unguarded(fn () => Event::create([
            ...$data, 'organization_id' => $org->id, 'status' => 'draft',
        ])));
        $this->audit->record($actor, 'event.created', Event::class, $event->id, ['organization_id' => $org->id]);
        return $event;
    }

    public function transitionTo(Event $event, string $next, User $actor, ?string $reason = null): Event
    {
        abort_unless(in_array($next, self::TRANSITIONS[$event->status] ?? [], true), 422, 'Transisi status tidak valid.');
        if ($next === 'cancelled') {
            abort_unless(trim((string) $reason) !== '', 422, 'Alasan pembatalan wajib.');
        }
        return DB::transaction(function () use ($event, $next, $actor, $reason): Event {
            $old = $event->status;
            $event->forceFill([
                'status' => $next,
                'published_at' => $next === 'published' ? now() : $event->published_at,
            ])->save();
            $this->audit->record($actor, "event.{$next}", Event::class, $event->id, [
                'organization_id' => $event->organization_id,
            ], ['status' => $old, 'reason' => $reason]);
            return $event->fresh();
        });
    }
    // ... updateEvent/deleteEvent (tolak bila isTerminal; delete = soft delete + audit),
    // createDivision/updateDivision/deleteDivision,
    // createRole/updateRole/deleteRole, createShift/updateShift/deleteShift:
    // masing-masing abort_unless(!$event->isTerminal(), 422, ...),
    // tulis via unguarded/forceFill untuk FK non-fillable, audit per aksi.
}
```

Aturan audit action: `event.created/updated/deleted/event.{state}`, `division.created/updated/deleted`, `role.created/updated/deleted`, `shift.created/updated/deleted`. Semua `record()` menyertakan `organization_id` + `event_id` (cek signature `AuditLogService::record` Phase 1 — sesuaikan argumen bila menerima `event_id`; bila tidak, sertakan dalam array konteks).

- [ ] **Step 4: Jalankan test**

Run: `docker compose exec app ./vendor/bin/pest tests/Unit/EventServiceTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Services/EventService.php tests/Unit/EventServiceTest.php
git commit -m "feat: event service CRUD + state machine"
```

---

### Task 3: Policy + route organizer + Form Request

**Files:**
- Create: `app/Policies/EventPolicy.php`, `app/Policies/DivisionPolicy.php`, `app/Policies/RolePolicy.php`, `app/Policies/ShiftPolicy.php`
- Create: `app/Http/Requests/StoreEventRequest.php`, `app/Http/Requests/UpdateEventRequest.php`, `app/Http/Requests/TransitionEventRequest.php`, `app/Http/Requests/ManageDivisionRequest.php`, `app/Http/Requests/ManageRoleRequest.php`, `app/Http/Requests/ManageShiftRequest.php`
- Create: `app/Http/Controllers/Organizer/EventController.php`, `app/Http/Controllers/Organizer/DivisionController.php`, `app/Http/Controllers/Organizer/RoleController.php`, `app/Http/Controllers/Organizer/ShiftController.php`
- Modify: `app/Providers/AppServiceProvider.php` (registrasi 4 policy + binding `event`, `division`, `role`, `shift` scoped), `routes/web.php` (group organizer events)
- Test: `tests/Feature/EventTest.php` (CRUD + transisi + 404-vs-403)

**Interfaces:**
- Consumes: Task 2 `EventService`; Phase 1 `organization` binding + `belongsToOrganization()`.
- Produces: route `organizer.events.*` (+ division/role/shift nested) untuk Task 5–6; binding `event/division/role/shift` untuk Task 4–5.

- [ ] **Step 1: Tulis failing feature test**

```php
it('owner bisa crud event dan transisi publish', function (): void {
    $owner = orgTesOwner(); $org = $owner->organizations()->first();
    $this->actingAs($owner);

    $res = $this->post(route('organizer.events.store', $org->slug), [
        'name' => 'Festival', 'slug' => 'festival',
        'start_at' => now()->addMonth()->toDateTimeString(),
        'end_at' => now()->addMonth()->addDays(2)->toDateTimeString(),
    ]);
    $res->assertRedirect();
    $event = Event::where('slug', 'festival')->firstOrFail();

    $this->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'published'])
        ->assertRedirect();
    expect($event->fresh()->status)->toBe('published');
});

it('staff tanpa permission ditolak 403, staff org lain 404', function (): void {
    // pola Phase 1 Task 4: staff satu org tanpa event.create → 403;
    // staff org lain akses slug org pertama → 404 (binding organization).
});

it('cancel tanpa alasan 422; tanpa password.confirm redirect', function (): void {
    // POST transition cancelled tanpa reason → 422;
    // tanpa sesi password.confirm → redirect route password.confirm.
});

it('mass assignment organization_id dan status ditolak', function (): void {
    // POST store dengan organization_id org lain + status published
    // → event tetap milik org sendiri + status draft.
});
```

Minimal 7 test (CRUD, transisi valid/invalid, cancel guard, 403/404, mass-assignment, division/role/shift nested 404 lintas event).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/EventTest.php`
Expected: FAIL (`Route [organizer.events.store] not defined`).

- [ ] **Step 3: Tulis 4 policy**

`EventPolicy`: `view` (member org — untuk daftar/lihat dalam organizer), `create` (`belongsToOrganization` + `can('event.create')`), `update` (+ `event.update`), `delete` (owner saja — SECURITY.md §5 delete/cancel owner-only), `publish` (`belongsToOrganization` + `can('event.publish')`). `DivisionPolicy`/`RolePolicy`/`ShiftPolicy`: `manage` (`belongsToOrganization($event->organization_id)` + `can('division.manage'|'role.manage'|'shift.manage')`) — resolve event via relasi (`$division->event`).

- [ ] **Step 4: Tulis 6 Form Request**

`StoreEventRequest::authorize` → `can('create', [Event::class, organization])` (via `$this->route('organization')`); rules: `name` required max 255, `slug` required regex slug + unique per org (`Rule::unique('events')->where('organization_id', $org->id)`), `start_at/end_at` required date `after:start_at`, `registration_*` nullable date, `category/venue/address` nullable max, `capacity` nullable integer min 0, `contact/branding` nullable array, `terms/privacy_notice` nullable string.

`UpdateEventRequest` versi `sometimes` (tanpa slug? slug boleh diubah — unique per org ignore id). `TransitionEventRequest::authorize` → `can('publish', event)`; rules `status` required in 8 state, `reason` required_if status cancelled max 2000. `ManageDivisionRequest`/`ManageRoleRequest`/`ManageShiftRequest`: `authorize` → `can('manage', model-dari-route)`; rules sesuai kolom fillable (role: `quota` integer min 0; shift: `start_at/end_at`, `role_id` nullable exists dalam event yang sama — validasi silang via `Rule::exists(...)->where('event_id', ...)`).

- [ ] **Step 5: Binding scoped + route + controller tipis**

`AppServiceProvider`: `Gate::policy()` ×4. Binding `event`: resolve by slug dalam org dari route (`request()->route()->parameter('organization')` sudah berupa Organization ter-binding Phase 1 — ambil id-nya; pola mirip binding `member` Phase 1). Binding `division`/`role`/`shift`: resolve by id dalam event dari route. Di luar scope → `firstOrFail()` → 404.

`routes/web.php` dalam group organizer Phase 1:

```php
Route::prefix('{organization}/events')->name('events.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('index');
    Route::get('create', [EventController::class, 'create'])->name('create');
    Route::post('/', [EventController::class, 'store'])->name('store');
    Route::prefix('{event}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('show');
        Route::get('edit', [EventController::class, 'edit'])->name('edit');
        Route::patch('/', [EventController::class, 'update'])->name('update');
        Route::delete('/', [EventController::class, 'destroy'])->name('destroy');
        Route::post('transition', [EventController::class, 'transition'])
            ->middleware('password.confirm')->name('transition');
        // division/role/shift nested: resource-style manual (index/create/store/show/update/destroy)
        // dengan nama organizer.events.divisions.* dst.
    });
});
```

PERHATIAN: `password.confirm` pada SEMUA transition akan memaksa re-auth bahkan untuk publish biasa. Spec §3 menyebut cancel wajib re-auth; keputusan implementasi: pasang `password.confirm` hanya untuk transisi ke `cancelled`? Middleware route tidak bisa kondisional — solusinya: route transition TANPA `password.confirm`, controller cek `$request->validateWithBag` ... pola Phase 1 memakai middleware route untuk transfer/suspend. Keputusan: pasang `password.confirm` pada route transition (seluruh transisi state adalah aksi sensitif — konsisten perlakuan "operasi sensitif" SECURITY.md §1). Dokumentasikan di laporan bila reviewer bertanya.

Controller tipis: `Gate::authorize` atau Form Request authorize (pilih Form Request untuk store/update/transition, `Gate::authorize` untuk show/index/destroy — konsisten Phase 1), panggil service, redirect + flash Indonesia.

- [ ] **Step 6: View Blade minimal** (index event org, form create/edit event, show event + daftar division/role/shift, form division/role/shift) — `x-app-layout`, prosa Indonesia, `@can` untuk tombol sensitif.
- [ ] **Step 7: Jalankan test sampai hijau + commit**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/EventTest.php
git status --porcelain
git add <file eksplisit>
git commit -m "feat: organizer event routes + policy + scoped binding"
```

---

### Task 4: Katalog publik + detail event

**Files:**
- Create: `app/Http/Controllers/PublicEventController.php`
- Modify: `routes/web.php` (route publik + throttle), view katalog + detail
- Test: `tests/Feature/PublicEventTest.php`

**Interfaces:**
- Consumes: Task 1 model + `scopePublished()`; Task 3 binding publik.
- Produces: route `events.index`, `events.show` untuk Task 6.

- [ ] **Step 1: Tulis failing test**

```php
it('guest bisa lihat katalog dengan search filter pagination', function (): void {
    // seed 15 event published + 1 draft; GET /events → 200, lihat 12 (page 1);
    // ?search=nama → hanya cocok; ?kategori=musik → filter; draft tak terlihat.
});

it('event non-publik menghasilkan 404 tanpa bocor', function (): void {
    // GET /events/{slug-draft} → 404; GET /events/{slug-cancelled} → 404.
});

it('detail publik menampilkan sisa kuota tanpa data internal', function (): void {
    // GET /events/{slug-published} → 200, lihat nama role + "Sisa 5";
    // tidak lihat email member / audit / tombol kelola.
});
```

Minimal 5 test (katalog, search, filter kategori+kota, pagination, 404 non-publik, detail kuota, throttle 429 bila mudah — opsional).

- [ ] **Step 2: Jalankan, pastikan gagal** (`Route [events.index] not defined`).
- [ ] **Step 3: Implementasi controller + route**

```php
Route::get('events', [PublicEventController::class, 'index'])
    ->middleware('throttle:60,1')->name('events.index');
Route::get('events/{eventPublic}', [PublicEventController::class, 'show'])
    ->middleware('throttle:60,1')->name('events.show');
```

Param `{eventPublic}` (bukan `{event}`) agar tidak terkena binding scoped organizer Task 3 — binding khusus: by slug + `published()` scope, gagal → 404. Query index: `Event::published()` + `when(search)` + `when(kategori)` + `when(kota→venue/address)` + `orderBy(start_at)` + `paginate(12)`. View: katalog + detail (sisa kuota via `remainingQuota()`, jadwal shift publik).

- [ ] **Step 4: Hijau + commit** (`feat: katalog publik event + search filter`).

---

### Task 5: Admin event + permission sync event

**Files:**
- Create: `app/Http/Controllers/Admin/EventController.php`, `tests/Feature/AdminEventTest.php`
- Modify: `routes/web.php` (group admin events), `app/Services/MembershipService.php` (GRANULAR sudah dari Task 1 — verifikasi sync mencakup perm baru), view admin
- Test: full `AdminEventTest` 6–8 test

**Interfaces:**
- Consumes: Task 1–4; Phase 1 admin group + `role:super_admin`.
- Produces: route `admin.events.*` untuk Task 6.

- [ ] **Step 1: Tulis failing test** (daftar semua event paginasi; cancel paksa butuh alasan + re-auth + audit; non-admin 403; guest redirect login — guest assertion di AWAL sebelum actingAs, pelajaran Phase 1 Task 5).
- [ ] **Step 2: Gagal** (`Route [admin.events.index] not defined`).
- [ ] **Step 3: Implementasi** — `Admin\EventController`: index (semua event + filter status/org, paginate 20), cancel paksa (`EventService::transitionTo` ke `cancelled` dengan reason; param `{eventAdmin}` by id global seperti pola `{org}` Phase 1 agar tak terkena binding scoped), suspend? TIDAK — event tidak punya status suspended; admin hanya cancel paksa + lihat. `password.confirm` pada cancel paksa. Verifikasi `syncPermissions` mencakup 8 perm baru (otomatis via GRANULAR — tulis 1 test sync owner dapat `event.create`).
- [ ] **Step 4: Hijau + commit** (`feat: admin event + cancel paksa`).

---

### Task 6: Authorization + isolation suite + gates + tutup

**Files:**
- Create: `tests/Feature/EventAuthorizationTest.php`, `tests/Feature/EventIsolationTest.php`
- Modify: `README.md` (status Phase 2)
- Test: full `./vendor/bin/pest`; `./vendor/bin/pint --test`; `./vendor/bin/phpstan analyse`; `composer audit`

**Interfaces:**
- Consumes: Task 1–5 (seluruh alur).
- Produces: Phase 2 hijau + README akurat + siap basis Phase 3.

- [ ] **Step 1: AuthorizationTest (matriks)** — tiap endpoint Task 3–5 × aktor (guest→login untuk organizer, 200 untuk publik; owner ok; staff ber-perm ok; staff tanpa perm 403; staff org lain 404; volunteer 403 organizer + 200 publik; non-admin ke /admin 403). Minimal 20 asersi rute. Helper prefix unik (`eventAuthz*`). Tamu selalu sesi segar.
- [ ] **Step 2: IsolationTest (simetris A↔B)** — Org A event/division/role/shift vs Org B: read/update/delete/transition silang → 404 kedua arah; katalog publik tak bocorkan event non-publik; volunteer luar → 403/404. Helper prefix unik (`eventIsolasi*`).
- [ ] **Step 3: Gates**

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec app composer audit
```

Expected: Pest hijau; Pint PASS (bila FAIL → `./vendor/bin/pint`, ulangi); PHPStan No errors (perbaiki di kode, bukan lemahkan config); audit tanpa critical/high.

- [ ] **Step 4: README + commit final**

Ubah `README.md` blok Status:

```text
Lama: **Phase 1 — Selesai.** Auth + organization + membership + RBAC + isolation + suite hijau. Lanjut Phase 2 (Event).
Baru: **Phase 2 — Selesai.** Event + division + role + shift + state machine + katalog publik + suite hijau. Lanjut Phase 3 (Volunteer).
```

```bash
git status --porcelain
git add <file eksplisit>
git commit -m "chore: phase 2 done (gates green) — ready for phase 3"
git log --oneline -n 10
```

Expected: rantai commit Phase 2 terlihat; status bersih.

---

## Self-Review

1. **Spec coverage:** §1 (EventService + 8 permission + tanpa QuotaService) → Task 1, 2, 5; §2 (4 tabel + kolom + constraint + relasi + scope) → Task 1; relasi/helper → Task 1; §3 (route groups + scoped binding ganda + policy + transaksi + fillable + state machine + katalog) → Task 3, 4, 5; §4 (unit/feature/auth/isolation/negatif/gates) → Task 1 (model), 2 (unit service), 3–5 (feature), 6 (matriks + isolasi + gates). Kolom upload disiapkan tanpa endpoint (§2) → Task 1 migrasi saja. Tanpa cache (§3) → tidak ada task cache. Registration window pasif → tidak ada scheduler.
2. **Placeholder scan:** seluruh test berisi kode aktual; signature service final; permission 8 buah eksplisit; nilai numerik konkret (pagination 12/20, throttle publik 60/menit, quota default 0, timezone Asia/Jakarta). Tanpa TBD/TODO.
3. **Type consistency:** `EventService::{createEvent, updateEvent, deleteEvent, transitionTo, ...}` dipakai konsisten Task 2→5; `TRANSITIONS` didefinisikan sekali di Task 2; helper `belongsToOrganization(int): bool`, `organizationRole(int): ?string` (Phase 1) dipakai policy Task 3; `remainingQuota(): int`, `isTerminal(): bool`, `isPubliclyVisible(): bool` didefinisikan Task 1 dipakai Task 2–4; `{eventPublic}`/`{eventAdmin}` dibedakan dari `{event}` scoped agar tak tabrakan binding.
