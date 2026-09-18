# Phase 3 (Volunteer) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Volunteer profile + registration + custom fields 13 tipe + seleksi/bulk + waitlist manual + quota atomik dengan suite hijau.

**Architecture:** Dua service baru — `RegistrationService` (satu-satunya penulis `status` registration) + `QuotaService` (satu-satunya penulis `accepted_count`, lock `FOR UPDATE`) — menempel rantai `User → Organization → Event → Role → Registration`. Controller tipis → Form Request → Service → Model, persis pola Phase 1–2.

**Tech Stack:** Laravel 13, PHP 8.4 (via `docker compose exec app`), PostgreSQL 16, Blade, Spatie permission, Pest, Pint, Larastan level 5.

**Spec:** `docs/superpowers/specs/2026-09-18-phase3-volunteer-design.md`

## Global Constraints

- Prose (Blade, flash, pesan error/validasi) Bahasa Indonesia; kode/identifier/branch/commit Bahasa Inggris.
- Setiap step konten nyata, tanpa TBD/TODO/placeholder.
- `git add` file eksplisit setelah `git status --porcelain`, NEVER `git add -A`.
- Akhiri setiap commit dengan `Co-Authored-By: Claude Code <noreply@anthropic.com>`.
- Kerja in place (tanpa worktree); semua PHP/composer/artisan via `docker compose exec app ...` (fallback `docker --context default compose exec app ...` bila daemon default context mati); Node/npm di host.
- Controller tipis → Form Request → Service → Model; permission = kemampuan AND membership = cakupan (`can()` + `belongsToOrganization()`); owner dapat semua perm org via sync; audit/security log append-only.
- Kolom sensitif (`user_id`, `event_id`, `role_id`, `status` registration; `accepted_count`) tidak fillable — server-side only.

---

### Task 1: Schema + models + permissions volunteer

**Files:**
- Create: `database/migrations/2026_09_18_000001_create_volunteer_profiles_table.php`, `2026_09_18_000002_create_event_custom_fields_tables.php`, `2026_09_18_000003_create_registrations_tables.php`
- Create: `app/Models/VolunteerProfile.php`, `app/Models/EventCustomField.php`, `app/Models/EventCustomFieldOption.php`, `app/Models/Registration.php`, `app/Models/RegistrationAnswer.php`, `app/Models/RegistrationStatusHistory.php`
- Create: `database/factories/VolunteerProfileFactory.php`, `database/factories/EventCustomFieldFactory.php`, `database/factories/RegistrationFactory.php`
- Modify: `app/Models/User.php` (+`volunteerProfile()`, +`registrations()`), `app/Models/Event.php` (+`registrations()`, +`customFields()`), `app/Models/EventRole.php` (+`registrations()`), `database/seeders/PermissionSeeder.php` (+2 perm), `app/Services/MembershipService.php` (GRANULAR +2)
- Test: `tests/Feature/VolunteerModelRelationTest.php`

**Interfaces:**
- Consumes: Phase 2 `Event`/`EventRole`, Phase 1 `User`, `MembershipService::GRANULAR`, `PermissionSeeder`.
- Produces: model + relasi + 2 permission (`registration.read`, `registration.review`) untuk Task 2–6; `Registration::TRANSITIONS` sebagai sumber tunggal transisi valid.

- [ ] **Step 1: Tulis migration volunteer_profiles**

```php
Schema::create('volunteer_profiles', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('full_name');
    $table->string('phone', 32)->nullable();
    $table->string('city', 128)->nullable();
    $table->string('education', 128)->nullable();
    $table->text('experience')->nullable();
    $table->jsonb('skills')->nullable();
    $table->string('portfolio_url', 512)->nullable();
    $table->jsonb('social_links')->nullable();
    $table->jsonb('availability')->nullable();
    $table->string('visibility', 32)->default('organizers_only');
    $table->date('date_of_birth')->nullable();
    $table->text('emergency_contact')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
DB::statement("ALTER TABLE volunteer_profiles ADD CONSTRAINT volunteer_profiles_visibility_check CHECK (visibility IN ('public','organizers_only','private'))");
```

Catatan: `Blueprint::check()` tidak ada di Laravel 13 (pelajaran Phase 2 Task 1) — semua check constraint via `DB::statement ALTER TABLE ...` setelah `Schema::create`.

- [ ] **Step 2: Tulis migration event_custom_fields + options**

```php
Schema::create('event_custom_fields', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->string('type', 32);
    $table->boolean('required')->default(false);
    $table->string('placeholder')->nullable();
    $table->string('validation_rule', 512)->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->index(['event_id', 'sort_order']);
});
DB::statement("ALTER TABLE event_custom_fields ADD CONSTRAINT event_custom_fields_type_check CHECK (type IN ('text','textarea','email','phone','number','date','time','select','multi_select','radio','checkbox','url','file'))");

Schema::create('event_custom_field_options', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('event_custom_field_id')->constrained('event_custom_fields')->cascadeOnDelete();
    $table->string('label');
    $table->string('value');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 3: Tulis migration registrations + answers + histories**

```php
Schema::create('registrations', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained('event_roles')->cascadeOnDelete();
    $table->string('status', 32)->default('pending');
    $table->timestampTz('submitted_at')->nullable();
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestampTz('reviewed_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->uuid('idempotency_key')->unique();
    $table->timestamps();
    $table->index(['event_id', 'status']);
    $table->index(['role_id', 'status']);
    $table->index(['user_id']);
});
DB::statement("ALTER TABLE registrations ADD CONSTRAINT registrations_status_check CHECK (status IN ('pending','under_review','accepted','rejected','waitlisted','cancelled','withdrawn'))");
DB::statement("CREATE UNIQUE INDEX registrations_user_event_active_uniq ON registrations (user_id, event_id) WHERE status IN ('pending','under_review','accepted','waitlisted')");

Schema::create('registration_answers', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignId('event_custom_field_id')->constrained('event_custom_fields')->cascadeOnDelete();
    $table->text('value_text')->nullable();
    $table->jsonb('value_jsonb')->nullable();
    $table->string('file_path', 512)->nullable();
    $table->timestamps();
    $table->unique(['registration_id', 'event_custom_field_id']);
});

Schema::create('registration_status_histories', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
    $table->string('from_status', 32)->nullable();
    $table->string('to_status', 32);
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('reason')->nullable();
    $table->timestampTz('created_at')->nullable();
    $table->index(['registration_id']);
});
```

- [ ] **Step 4: Tulis 6 model + relasi balik + permission**

`VolunteerProfile`: fillable `full_name,phone,city,education,experience,skills,portfolio_url,social_links,availability,visibility,date_of_birth,emergency_contact` (tanpa `user_id`); casts `skills/social_links/availability array`, `date_of_birth date`; `belongsTo User`.
`EventCustomField`: fillable `label,type,required,placeholder,validation_rule,sort_order,is_active` (tanpa `event_id`); casts `required/is_active boolean`; `belongsTo Event`, `hasMany Option`; konstanta `TYPES` 13 tipe.
`EventCustomFieldOption`: fillable `label,value,sort_order`; `belongsTo field`.
`Registration`: fillable `submitted_at,reviewed_by,reviewed_at,rejection_reason` saja (tanpa `user_id,event_id,role_id,status,idempotency_key`); casts datetime; `belongsTo user/event/role`, `hasMany answers/histories`; konstanta `TRANSITIONS` = `['pending' => ['under_review','cancelled','withdrawn'], 'under_review' => ['accepted','rejected','waitlisted','cancelled','withdrawn'], 'waitlisted' => ['accepted','rejected','cancelled','withdrawn'], 'accepted' => ['cancelled'], 'rejected' => [], 'cancelled' => [], 'withdrawn' => []]`; helper `isTerminal()`, `isActive()`.
`RegistrationAnswer`: fillable `value_text,value_jsonb,file_path`; casts `value_jsonb array`.
`RegistrationStatusHistory`: fillable `from_status,to_status,reason` saja (tanpa `registration_id,changed_by`).
Relasi balik: `User::volunteerProfile() hasOne`, `User::registrations() hasMany`; `Event::registrations()`, `Event::customFields()`; `EventRole::registrations()`.
`PermissionSeeder` + `MembershipService::GRANULAR` tambah `registration.read`, `registration.review` (append di akhir array; `OWNER_PERMS = GRANULAR` otomatis ikut; `STAFF_BASE` tidak berubah).

- [ ] **Step 5: Tulis factories + relation test dulu (TDD), jalankan sampai gagal**

`VolunteerProfileFactory`: `user_id` dari `User::factory()`, `full_name` faker name, visibility `organizers_only`. `EventCustomFieldFactory`: `event_id` dari `Event::factory()`, type `text`, `sort_order` 0, `is_active` true. `RegistrationFactory`: buat `EventRole` dulu (pola Phase 2: role ikut division→event), lalu `event_id` dari `$role->event_id`, `user_id` dari `User::factory()`, status `pending`, `idempotency_key` `Str::uuid()`.

`tests/Feature/VolunteerModelRelationTest.php` (3 test): profil belongsTo user + user hasOne profil; registration→user/event/role + duplikat aktif ditolak (`QueryException` dibungkus `DB::transaction` per statement — pelajaran Phase 2 Task 1 soal 25P02); custom field→options + check 13 tipe menolak tipe invalid.

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/VolunteerModelRelationTest.php`
Expected: FAIL with "table not found / class not found".

- [ ] **Step 6: Implementasi model/migration/factory sampai hijau**

Run ulang test di atas.
Expected: PASS (3 test).

- [ ] **Step 7: Commit**

```bash
git status --porcelain
git add database/migrations/2026_09_18_000001_create_volunteer_profiles_table.php database/migrations/2026_09_18_000002_create_event_custom_fields_tables.php database/migrations/2026_09_18_000003_create_registrations_tables.php app/Models/VolunteerProfile.php app/Models/EventCustomField.php app/Models/EventCustomFieldOption.php app/Models/Registration.php app/Models/RegistrationAnswer.php app/Models/RegistrationStatusHistory.php database/factories/VolunteerProfileFactory.php database/factories/EventCustomFieldFactory.php database/factories/RegistrationFactory.php app/Models/User.php app/Models/Event.php app/Models/EventRole.php database/seeders/PermissionSeeder.php app/Services/MembershipService.php tests/Feature/VolunteerModelRelationTest.php
git commit -m "feat: volunteer schema + models + permissions

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 2: QuotaService + RegistrationService + unit test

**Files:**
- Create: `app/Services/QuotaService.php`, `app/Services/RegistrationService.php`, `app/Exceptions/QuotaFullException.php`
- Test: `tests/Feature/RegistrationServiceTest.php`, `tests/Feature/QuotaServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models + `Registration::TRANSITIONS`; `AuditLogService::record(User,string,string,int|string,array)`; `EventRole::accepted_count`.
- Produces: `RegistrationService::submit/withdraw/review/bulkReview/cancel`, `QuotaService::accept/release` untuk Task 3–5.

- [ ] **Step 1: Tulis QuotaServiceTest dulu (TDD)**

Helper prefix unik `quotaTes*` (cek tak bentrok `eventTes*` Phase 2). Setup: org aktif + owner + event `registration_open` + role quota 2.

```php
it('accept menaikkan counter saat slot tersedia', function (): void {
    $s = quotaTesSetup(2);
    app(QuotaService::class)->accept($s['role']);
    expect($s['role']->refresh()->accepted_count)->toBe(1);
});
it('accept penuh melempar QuotaFullException', function (): void {
    $s = quotaTesSetup(1);
    app(QuotaService::class)->accept($s['role']);
    expect(fn () => app(QuotaService::class)->accept($s['role']))->toThrow(QuotaFullException::class);
});
it('release menurunkan counter dan tidak negatif', function (): void {
    // release pada 0 → tetap 0, tidak negatif
});
```

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/QuotaServiceTest.php`
Expected: FAIL with "class not found".

- [ ] **Step 2: Implementasi QuotaService + QuotaFullException**

```php
namespace App\Exceptions;
use Symfony\Component\HttpKernel\Exception\HttpException;
class QuotaFullException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'Kuota role sudah penuh.');
    }
}
```

```php
class QuotaService
{
    public function accept(EventRole $role): EventRole
    {
        $terkunci = EventRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->accepted_count >= $terkunci->quota) {
            throw new QuotaFullException;
        }
        $terkunci->increment('accepted_count');
        return $terkunci->refresh();
    }

    public function release(EventRole $role): EventRole
    {
        $terkunci = EventRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->accepted_count > 0) {
            $terkunci->decrement('accepted_count');
        }
        return $terkunci->refresh();
    }
}
```

Keputusan: `accept()` melempar `QuotaFullException` (422) — bukan return bool — agar pemanggil service/controller cukup try/catch satu tipe. `release()` idempotent di nol.

- [ ] **Step 3: Tulis RegistrationServiceTest dulu (TDD)**

Helper prefix `regTes*`. 6 test: submit → pending (+answers +history); submit duplikat aktif → 422; withdraw milik sendiri → withdrawn; withdraw milik orang → 403/404 (via policy, cukup service-level: withdraw user lain ditolak); review under_review→accepted menaikkan counter; transisi invalid (pending→accepted langsung) ditolak 422; volunteer tidak bisa set accepted (submit dengan status accepted diabaikan → tetap pending).

Run sampai gagal (`Class "App\Services\RegistrationService" not found`).

- [ ] **Step 4: Implementasi RegistrationService**

```php
class RegistrationService
{
    public function __construct(private AuditLogService $audit, private QuotaService $quota) {}

    public function submit(Event $event, EventRole $role, User $user, array $answers, string $idempotencyKey): Registration
    {
        abort_unless($event->status === 'registration_open', 422, 'Registrasi event ini tidak sedang dibuka.');
        abort_if($event->isTerminal(), 422, 'Event sudah berakhir atau dibatalkan.');
        abort_unless((int) $role->event_id === (int) $event->id, 422, 'Role tidak termasuk event ini.');
        // requirement: user aktif + org aktif (cek via membership/status)
        return DB::transaction(function () use ($event, $role, $user, $answers, $idempotencyKey): Registration {
            $existing = Registration::where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Registration) {
                return $existing; // idempotent replay
            }
            $reg = Registration::unguarded(fn (): Registration => Registration::create([
                'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id,
                'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => $idempotencyKey,
            ]));
            // simpan answers (divalidasi di Form Request; service percaya payload tervalidasi)
            foreach ($answers as $jawab) { $reg->answers()->create($jawab); }
            $reg->histories()->create(['from_status' => null, 'to_status' => 'pending', 'changed_by' => $user->id, 'reason' => null]);
            $this->audit->record($user, 'registration.submitted', Registration::class, $reg->id, ['organization_id' => $event->organization_id, 'event_id' => $event->id]);
            return $reg;
        });
    }
```

`withdraw(Registration $reg, User $user)`: hanya pemilik (`$reg->user_id === $user->id`, bila tidak → 403); transisi harus ada di `TRANSITIONS[$reg->status]` (accepted tidak bisa withdraw — hanya cancel organizer); bila status `accepted` → tak tercapai (withdraw dari accepted ditolak transisi). `review(Registration $reg, string $to, User $actor, ?string $reason)`: validasi transisi via `TRANSITIONS`; `rejected` wajib reason; `accepted` → `$this->quota->accept($reg->role)` dalam transaction yang sama (lock di dalam); `cancelled` dari accepted → `$this->quota->release()`; tulis history + audit `registration.{to}`. `bulkReview(Event $event, array $ids, string $to, User $actor, ?string $reason)`: maks 50 ID (`abort_if(count($ids) > 50, 422, ...)`); semua ID harus milik event (`whereIn + count` cocok, bila tidak → 404 fail-closed); loop `review()` per item dalam SATU transaction (lock berurutan per role; gagal satu → rollback semua). `cancel()` = alias review ke `cancelled` oleh organizer (melepas quota bila accepted).

- [ ] **Step 5: Jalankan kedua test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/QuotaServiceTest.php tests/Feature/RegistrationServiceTest.php`
Expected: PASS (9 test: 3 quota + 6 registration).

- [ ] **Step 6: Commit**

```bash
git status --porcelain
git add app/Services/QuotaService.php app/Services/RegistrationService.php app/Exceptions/QuotaFullException.php tests/Feature/QuotaServiceTest.php tests/Feature/RegistrationServiceTest.php
git commit -m "feat: quota + registration service + state machine

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 3: Custom fields organizer + validasi jawaban + file upload

**Files:**
- Create: `app/Http/Requests/ManageCustomFieldRequest.php`, `app/Http/Controllers/Organizer/CustomFieldController.php`
- Modify: `routes/web.php` (nested `fields` di bawah `{event}`), `app/Providers/AppServiceProvider.php` (binding `field` scoped), view Blade `resources/views/organizer/events/fields/*.blade.php` (index/create/edit minimal)
- Test: `tests/Feature/CustomFieldTest.php`

**Interfaces:**
- Consumes: Task 1 `EventCustomField` + `TYPES`; Task 2 service (tidak langsung — CRUD field mandiri + audit).
- Produces: route `organizer.events.fields.*` + binding `field`; field aktif untuk Task 4 (form volunteer).

- [ ] **Step 1: Tulis CustomFieldTest dulu (TDD, 7 test)**

Helper prefix `fieldTes*`. Test: buat field text required + options select; tipe invalid ditolak 422; option untuk tipe text ditolak (hanya 4 tipe opsi); update + nonaktifkan; hapus field (cascade options); staff tanpa perm → 403; lintas event → 404.

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/CustomFieldTest.php`
Expected: FAIL with "Route [organizer.events.fields.store] not defined".

- [ ] **Step 2: Implementasi ManageCustomFieldRequest**

```php
public function authorize(): bool
{
    return $this->user()->can('manage', [EventCustomField::class, $this->route('event')]);
    // ATAU ikut pola Phase 2: Gate policy baru CustomFieldPolicy::manage (member + role.manage?)
}
```

Keputusan eksplisit: permission baru TIDAK dibuat untuk fields — gunakan `role.manage` (field adalah bagian definisi role/event, dikelola siapa yang kelola role). `rules()`: `label` required max 255; `type` required `in:` + 13 tipe; `required` boolean; `placeholder` nullable max 255; `validation_rule` nullable max 512; `sort_order` integer min 0; `is_active` boolean; `options` array hanya bila type in select/multi_select/radio/checkbox (closure: tiap option `label`+`value` required); bila type lain + options terisi → 422.

- [ ] **Step 3: Controller + binding + routes + views**

`CustomFieldController` tipis (index/create/store/show/edit/update/destroy), pola `RoleController` Phase 2: `Gate::authorize('manage', ...)` untuk index/show/destroy, FormRequest untuk store/update, redirect flash Indonesia. Binding `field` di `AppServiceProvider`: by id dalam event (`where event_id`), luar scope → 404. Routes nested `Route::prefix('fields')->name('fields.')` di bawah `{event}` setelah `shifts`. Views minimal index/create/edit dengan `@can`.

- [ ] **Step 4: Jalankan test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/CustomFieldTest.php`
Expected: PASS (7 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Http/Requests/ManageCustomFieldRequest.php app/Http/Controllers/Organizer/CustomFieldController.php routes/web.php app/Providers/AppServiceProvider.php resources/views/organizer/events/fields/ tests/Feature/CustomFieldTest.php
git commit -m "feat: organizer custom fields + scoped binding

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 4: Registrasi volunteer + profil + upload jawaban

**Files:**
- Create: `app/Http/Requests/StoreRegistrationRequest.php`, `app/Http/Requests/UpdateVolunteerProfileRequest.php`, `app/Http/Controllers/VolunteerRegistrationController.php`, `app/Http/Controllers/VolunteerProfileController.php`
- Modify: `routes/web.php` (route volunteer), views `resources/views/registrations/*`, `resources/views/profile/volunteer.blade.php`, `resources/views/events/show.blade.php` (tombol/form daftar)
- Test: `tests/Feature/VolunteerRegistrationTest.php`, `tests/Feature/VolunteerProfileTest.php`

**Interfaces:**
- Consumes: Task 2 `RegistrationService::submit/withdraw`; Task 3 fields aktif; binding `eventPublic` Task 4 Phase 2.
- Produces: route `registrations.*` + `profile.volunteer.*` untuk Task 6.

- [ ] **Step 1: Tulis VolunteerRegistrationTest dulu (TDD, 8 test)**

Helper prefix `daftarTes*`. Test: submit sukses (role + jawaban valid → pending, answers tersimpan, history ada); submit tanpa profil → 422 ARAHKAN buat profil dulu? Keputusan: profil WAJIB sebelum submit (abort 422 'Lengkapi profil volunteer dulu.') — data minimization butuh identitas dasar. Jawaban required kosong → 422; option di luar event → 422; double-submit idempotency key sama → 1 record (200 existing); withdraw milik sendiri → withdrawn; withdraw milik orang → 404; event draft → 422; guest → redirect login (assertion di AWAL test file, pelajaran Phase 1).

Run sampai gagal (`Route [registrations.store] not defined`).

- [ ] **Step 2: Implementasi StoreRegistrationRequest (validasi jawaban dinamis)**

```php
public function rules(): array
{
    /** @var Event $event */
    $event = $this->route('eventPublic');
    $fields = $event->customFields()->with('options')->where('is_active', true)->orderBy('sort_order')->get();
    $rules = ['role_id' => ['required', 'integer', Rule::exists('event_roles', 'id')->where('event_id', $event->id)], 'idempotency_key' => ['required', 'uuid']];
    foreach ($fields as $f) {
        $key = "answers.{$f->id}";
        $r = $f->required ? ['required'] : ['nullable'];
        $rules[$key] = match ($f->type) {
            'email' => [...$r, 'email', 'max:255'],
            'number' => [...$r, 'numeric'],
            'date' => [...$r, 'date'],
            'time' => [...$r, 'date_format:H:i'],
            'url' => [...$r, 'url', 'max:512'],
            'phone' => [...$r, 'string', 'max:32'],
            'select', 'radio' => [...$r, Rule::in($f->options->pluck('value')->all())],
            'multi_select', 'checkbox' => [...$r, 'array', Rule::in(...)] // validasi tiap elemen via closure/key.* 
            'file' => [...$r, 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png', /* signature via closure getimagesize/finfo */],
            default => [...$r, 'string', 'max:2000'],
        };
    }
    return $rules;
}
```

File: validasi signature header server-side via closure (`finfo_file` MIME vs ekstensi), batas 5MB, simpan via `Storage::putFileAs('registration-answers', $file, Str::uuid().ext allowlist)` — direktori non-executable, nama acak; penolakan → `SecurityService::record(null,'file_upload_rejected',...)`. Multi/checkbox: aturan `answers.{id}.*` in options.

- [ ] **Step 3: Controller registrasi + profil + routes + views**

`VolunteerRegistrationController`: index (milik sendiri, paginate 12), show (own-only 404), create (form: role select + fields dinamis, dari `events.show`), store (profil wajib → service submit → redirect status), withdraw (own-only). `VolunteerProfileController`: edit/update milik sendiri (FormRequest: full_name required, phone/city/dll nullable, visibility in 3, date_of_birth date sebelum hari ini). Routes: `Route::middleware(['auth','verified'])->group`: `get registrations`, `get registrations/{registrationVol}`, `post events/{eventPublic}/register`, `post registrations/{registrationVol}/withdraw`, `get/patch profile/volunteer`. Binding `registrationVol`: by id + `user_id = auth()->id()` + event publik? Keputusan: scoped ke pemilik SAJA (event boleh apapun — volunteer lihat riwayat miliknya termasuk event ongoing); luar milik → 404. Throttle submit `throttle:10,1`.

- [ ] **Step 4: Jalankan kedua test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/VolunteerRegistrationTest.php tests/Feature/VolunteerProfileTest.php`
Expected: PASS (8 + 4 test profil: create/update/visibility/DOB).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Http/Requests/StoreRegistrationRequest.php app/Http/Requests/UpdateVolunteerProfileRequest.php app/Http/Controllers/VolunteerRegistrationController.php app/Http/Controllers/VolunteerProfileController.php routes/web.php resources/views/registrations/ resources/views/profile/volunteer.blade.php resources/views/events/show.blade.php tests/Feature/VolunteerRegistrationTest.php tests/Feature/VolunteerProfileTest.php app/Providers/AppServiceProvider.php
git commit -m "feat: volunteer registration + profile + custom answers

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 5: Seleksi organizer (review + bulk) + admin read-only

**Files:**
- Create: `app/Http/Requests/ReviewRegistrationRequest.php`, `app/Http/Requests/BulkReviewRequest.php`, `app/Http/Controllers/Organizer/RegistrationController.php`, `app/Http/Controllers/Admin/RegistrationController.php`
- Create: `app/Policies/RegistrationPolicy.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (`Gate::policy` + binding `registration` scoped event), `MembershipService` TIDAK (perm sudah Task 1 — tulis 1 test sync), views organizer + admin
- Test: `tests/Feature/OrganizerReviewTest.php`, `tests/Feature/AdminRegistrationTest.php`

**Interfaces:**
- Consumes: Task 2 `review/bulkReview`; binding `event` Phase 2; `GRANULAR` Task 1.
- Produces: route `organizer.events.registrations.*` + `admin.registrations.*` untuk Task 6.

- [ ] **Step 1: Tulis OrganizerReviewTest dulu (TDD, 8 test)**

Helper prefix `seleksiTes*`. Test: index filter status + pagination; under_review→accepted (counter++); →rejected wajib reason (tanpa reason 422); →waitlisted; accept quota penuh → 422; bulk accept 3 → semua accepted; bulk 1 ID lintas event → 404 + tidak ada yang berubah (rollback); staff tanpa `registration.review` → 403; guest di AWAL → redirect login.

Run sampai gagal (`Route [organizer.events.registrations.index] not defined`).

- [ ] **Step 2: Policy + requests + binding**

`RegistrationPolicy`: `viewAny/view` (member + `registration.read`); `review` (member + `registration.review`); `withdraw` tidak di sini (volunteer own-only di controller). Binding `registration`: by id dalam event (pola `division` Phase 2), luar scope → 404. `ReviewRegistrationRequest::authorize`: `can('review', $this->route('registration'))`; rules: `action in accepted,rejected,waitlisted,cancelled`, `reason` required_if rejected max 2000. `BulkReviewRequest`: `ids` array min 1 max 50 distinct integer; `action` sama; reason required_if rejected.

- [ ] **Step 3: Controller organizer + admin + routes + views**

`Organizer/RegistrationController`: index (`where event + when status + paginate 15 + with user,role`), show (`with answers.field, histories`), review (try/catch `QuotaFullException` → back withErrors), bulkReview (sama). `password.confirm` pada review/bulk? Keputusan: YA (aksi sensitif pelepas quota, konsisten transition Phase 2). `Admin/RegistrationController`: index lintas org (filter status + org, paginate 20, read-only — tanpa show mutasi; show boleh read-only). Routes organizer nested `registrations` di bawah `{event}`; admin `get registrations` + `get registrations/{registrationAdmin}` (by-id global, pola Phase 2).

- [ ] **Step 4: Tulis AdminRegistrationTest (6 test) + sync test**

Helper `adminReg*`: guest di AWAL redirect; lintas org terlihat; filter status+org; non-admin 403; owner dapat `registration.review` via sync (1 test sync); show read-only 200 tanpa tombol mutasi (assertDontSee 'Tolak'/'Terima'? — sesuaikan teks view).

- [ ] **Step 5: Jalankan kedua test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OrganizerReviewTest.php tests/Feature/AdminRegistrationTest.php`
Expected: PASS (8 + 6 test).

- [ ] **Step 6: Commit**

```bash
git status --porcelain
git add app/Http/Requests/ReviewRegistrationRequest.php app/Http/Requests/BulkReviewRequest.php app/Http/Controllers/Organizer/RegistrationController.php app/Http/Controllers/Admin/RegistrationController.php app/Policies/RegistrationPolicy.php routes/web.php app/Providers/AppServiceProvider.php resources/views/organizer/events/registrations/ resources/views/admin/registrations/ tests/Feature/OrganizerReviewTest.php tests/Feature/AdminRegistrationTest.php
git commit -m "feat: organizer review + bulk + admin read-only

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 6: Authorization + isolation + concurrency + gates + tutup

**Files:**
- Create: `tests/Feature/RegistrationAuthorizationTest.php`, `tests/Feature/RegistrationIsolationTest.php`, `tests/Feature/QuotaConcurrencyTest.php`
- Modify: `README.md` (status Phase 3)
- Test: full `./vendor/bin/pest`; `./vendor/bin/pint --test`; `./vendor/bin/phpstan analyse`; `composer audit`

**Interfaces:**
- Consumes: Task 1–5 (seluruh alur).
- Produces: Phase 3 hijau + README akurat + siap basis Phase 4.

- [ ] **Step 1: Tulis RegistrationAuthorizationTest (matriks, min 20 asersi rute)**

Helper prefix unik `regAuthz*` (cek tak bentrok `eventAuthz*`, `authz*` Phase 1, `regTes*`, `seleksiTes*`). Tiap endpoint Task 4–5 × aktor: guest → redirect login (volunteer+organizer); owner ok; staff ber-perm ok; staff tanpa perm 403; staff org lain 404; volunteer 403 milik orang + 200 milik sendiri; non-admin ke `/admin/registrations` 403. Tamu selalu sesi segar (tanpa `actingAs` sebelumnya di case yang sama — pelajaran Phase 1).

- [ ] **Step 2: Tulis RegistrationIsolationTest (simetris A↔B)**

Helper `regIsolasi*`. Org A vs B: registration/answer/field milik A diakses B → 404 kedua arah (read/update/review/bulk); katalog publik tak bocorkan jawaban; volunteer luar → 404. Data-intact check setelah upaya silang.

- [ ] **Step 3: Tulis QuotaConcurrencyTest**

Role quota 1, 3 user: submit ketiganya, lalu accept via 3 proses anak dengan `pcntl_fork` (tanpa dependensi baru; skip test bila `pcntl_fork` tidak tersedia dengan `markTestSkipped('pcntl tidak tersedia')`), tiap anak koneksi DB sendiri (`DB::reconnect()` setelah fork) + `QuotaService::accept()` + catat hasil ke file temp terpisah per PID. Induk `pcntl_waitpid` ketiganya lalu asert: tepat 1 accepted + `accepted_count ≤ quota` + 2 lainnya `QuotaFullException`. Test tambahan (sequential, tanpa fork): double-accept → counter naik 1x; accept-bersamaan-cancel (accept lalu cancel → counter kembali 0, status cancelled). Wajib melawan PostgreSQL (RefreshDatabase + pgsql test — sudah pola repo).

- [ ] **Step 4: Gates**

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec app composer audit
```

Expected: Pest hijau penuh; Pint PASS (bila FAIL → `./vendor/bin/pint`, ulangi); PHPStan No errors (perbaiki di kode, bukan lemahkan config); audit tanpa critical/high.

- [ ] **Step 5: README + commit final**

Ubah blok Status persis:
Lama: `**Phase 2 — Selesai.** Event + division + role + shift + state machine + katalog publik + suite hijau. Lanjut Phase 3 (Volunteer).`
Baru: `**Phase 3 — Selesai.** Volunteer profile + registration + custom fields + seleksi + quota atomik + suite hijau. Lanjut Phase 4 (Operations).`
(verifikasi string lama ada sebelum diganti). Lalu `git status --porcelain`, `git add` eksplisit, commit `chore: phase 3 done (gates green) — ready for phase 4`, `git log --oneline -n 10`.

Laporkan: commit hash, output keempat gates (angka persis), daftar file. TIDAK perlu review-package; reviewer terpisah menanganinya.
