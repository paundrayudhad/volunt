# Phase 5A (Certificate) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sertifikat volunteer pasca-event: eligibility ambang per event, batch terbitkan organizer, PDF unduhan own-only, verifikasi publik minimal, revoke, penutupan assignment → `completed`.

**Architecture:** Modul domain baru `Certificate` menempel rantai Phase 4 (`Assignment → Attendance → Certificate`). `CertificateService` satu-satunya penulis `certificates`; `AssignmentService::complete()` baru satu-satunya jalur ke status `completed` (menutup utang non-scope Phase 4). PDF via `barryvdh/laravel-dompdf`, generate-on-download (tanpa file tersimpan).

**Tech Stack:** Laravel 13, PHP 8.4 (container `app`), PostgreSQL 16, Blade, Spatie permission, Pest, Pint, Larastan level 5, `barryvdh/laravel-dompdf` (baru), QR PNG inline (library diputuskan Task 1: `bacon/bacon-qr-code` bila belum ada di vendor).

**Spec:** `docs/superpowers/specs/2026-09-20-phase5a-certificate-design.md`

## Global Constraints

- Semua PHP/composer/artisan via `docker compose exec app ...` (fallback `docker --context default compose exec app ...`).
- Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model; seluruh business logic di service.
- Permission = kemampuan AND membership = cakupan (`can()` + `belongsToOrganization()`); owner dapat semua perm via sync; `STAFF_BASE` tetap view-only.
- Audit/security log append-only; check constraints via `DB::statement`.
- Prose Indonesia, kode/identifier/branch/commit English (`$valid` dipertahankan); setiap step konten nyata tanpa TBD/TODO/placeholder.
- `git add` file eksplisit setelah `git status --porcelain`, NEVER `git add -A` (juga NEVER `-f`); akhiri commit dengan `Co-Authored-By: Claude Code <noreply@anthropic.com>`.
- Kerja in place (tanpa worktree); `QUEUE_CONNECTION=database` (sync di phpunit).
- Format nomor sertifikat: `WV-{tahun}-{6 alnum uppercase}` (contoh `WV-2026-A3F9K2`).

---

### Task 1: Skema + model + permission + dependensi PDF/QR

**Files:**
- Create: `database/migrations/2026_09_20_000001_create_certificates_tables.php`
- Create: `database/migrations/2026_09_20_000002_add_certificate_threshold_to_events.php`
- Create: `app/Models/Certificate.php`
- Create: `app/Models/CertificateVerification.php`
- Create: `database/factories/CertificateFactory.php`
- Create: `tests/Feature/CertificateModelRelationTest.php`
- Modify: `app/Models/Event.php:82-98` (tambah relasi `certificates()`), `app/Models/User.php` (tambah relasi `certificates()` — cari blok relasi yang ada), `app/Models/Registration.php` (tambah relasi `certificate()` — cari blok relasi yang ada)
- Modify: `app/Services/MembershipService.php:14-44` (GRANULAR +3), `database/seeders/PermissionSeeder.php:11-41` (PERMISSIONS +3)
- Modify: `composer.json` (require `barryvdh/laravel-dompdf`)

**Interfaces:**
- Consumes: pola migrasi `2026_09_18_000005_create_operations_tables.php` (check via `DB::statement`, FK `cascadeOnDelete`, index); pola `PermissionSeeder::PERMISSIONS` + `MembershipService::GRANULAR`.
- Produces: `Certificate::{certificateNo(qrTokenHash), isRevoked()}`, `CertificateVerification` (append-only, tanpa fillable tulis dari controller), `Event::certificates()`, permission `certificate.issue|revoke|read`, `CertificateService::DEFAULT_THRESHOLD = 50` (didefinisikan Task 2, ambang efektif Task 2).

- [ ] **Step 1: Tambah dependensi PDF + verifikasi QR tersedia**

Run: `docker compose exec app composer require barryvdh/laravel-dompdf --no-interaction`
Expected: sukses; `composer.json` bertambah satu require.

```bash
docker compose exec app php -r "var_dump(class_exists('BaconQrCode\Writer'));"
```

Expected: jika `false`, tambahkan juga: `docker compose exec app composer require bacon/bacon-qr-code --no-interaction`. Jika `true`, lewati. Catat hasil di report (reviewer Task 4 butuh tahu class QR yang tersedia).

- [ ] **Step 2: Tulis migrasi certificates + verifications**

```php
// database/migrations/2026_09_20_000001_create_certificates_tables.php
Schema::create('certificates', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('certificate_no', 32)->unique();
    $table->timestampTz('issued_at');
    $table->timestampTz('revoked_at')->nullable();
    $table->text('revoke_reason')->nullable();
    $table->string('qr_token_hash', 64)->unique();
    $table->timestamps();
    $table->unique(['event_id', 'user_id']);
    $table->index(['event_id', 'revoked_at']);
    $table->index(['user_id']);
});

Schema::create('certificate_verifications', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('verified_at');
    $table->string('ip', 64)->nullable();
    $table->string('user_agent', 512)->nullable();
    $table->index(['certificate_id']);
});
```

Expected: file tertulis. `revoke_reason` text nullable (alasan min 10 karakter divalidasi di Form Request Task 3, bukan check).

- [ ] **Step 3: Tulis migrasi ambang per event**

```php
// database/migrations/2026_09_20_000002_add_certificate_threshold_to_events.php
Schema::table('events', function (Blueprint $table): void {
    $table->unsignedSmallInteger('certificate_min_attendance_pct')->nullable();
});
DB::statement('ALTER TABLE events ADD CONSTRAINT events_cert_pct_check CHECK (certificate_min_attendance_pct IS NULL OR (certificate_min_attendance_pct BETWEEN 1 AND 100))');
```

down(): drop constraint + drop column. Expected: file tertulis.

- [ ] **Step 4: Tulis model + relasi + permission**

```php
// app/Models/Certificate.php
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    protected $fillable = []; // semua tulis via service (unguarded), pola Assignment/Attendance

    /** @var array<string, string> */
    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** @return BelongsTo<Registration, $this> */
    public function registration(): BelongsTo { return $this->belongsTo(Registration::class); }
    /** @return HasMany<CertificateVerification, $this> */
    public function verifications(): HasMany { return $this->hasMany(CertificateVerification::class); }

    public function isRevoked(): bool { return $this->revoked_at !== null; }
}
```

`CertificateVerification`: model polos + relasi `certificate()`, `$fillable = []`, cast `verified_at => datetime`. Relasi balik: `Event::certificates()`, `User::certificates()`, `Registration::certificate()` (hasOne). GRANULAR + PERMISSIONS tambah `certificate.issue`, `certificate.revoke`, `certificate.read` (setelah `announcement.read`, urutan akhir).

- [ ] **Step 5: Tulis factory + failing test, jalankan, implementasi hingga hijau**

```php
// tests/Feature/CertificateModelRelationTest.php
it('sertTesRelasiSertifikat', function (): void {
    $sertifikat = Certificate::factory()->create();
    expect($sertifikat->event)->toBeInstanceOf(Event::class)
        ->and($sertifikat->user)->toBeInstanceOf(User::class)
        ->and($sertifikat->registration)->toBeInstanceOf(Registration::class)
        ->and($sertifikat->isRevoked())->toBeFalse();
});

it('sertTesAmbangDefaultNull', function (): void {
    $event = Event::factory()->create();
    expect($event->certificate_min_attendance_pct)->toBeNull();
});

it('sertTesCheckConstraintAmbang', function (): void {
    $event = Event::factory()->create();
    expect(fn () => $event->forceFill(['certificate_min_attendance_pct' => 0])->save())
        ->toThrow(QueryException::class);
    expect(fn () => $event->forceFill(['certificate_min_attendance_pct' => 101])->save())
        ->toThrow(QueryException::class);
});

it('sertTesUnikEventUser', function (): void {
    $sertifikat = Certificate::factory()->create();
    expect(fn () => Certificate::factory()->create([
        'event_id' => $sertifikat->event_id,
        'user_id' => $sertifikat->user_id,
    ]))->toThrow(QueryException::class);
});
```

Run: `docker compose exec app php artisan test tests/Feature/CertificateModelRelationTest.php`
Expected: FAIL ("class not found") → tulis factory (sambungkan event+user+registration konsisten satu event; `certificate_no` format `WV-{tahun}-{6 alnum}`; `qr_token_hash` = `hash('sha256', Str::random(64))`; `issued_at` = now) → PASS 4 test.

- [ ] **Step 6: Verifikasi + commit**

Run: `docker compose exec app php artisan migrate --force` (harus hijau di dev DB),
`docker compose exec app ./vendor/bin/pint --test <semua file tersentuh>`,
`docker compose exec app ./vendor/bin/phpstan analyse <file app/> --level=5 --no-progress`,
`docker compose exec app php artisan test tests/Feature/CertificateModelRelationTest.php`.
Expected: semua hijau.

```bash
git status --porcelain
git add database/migrations/2026_09_20_000001_create_certificates_tables.php database/migrations/2026_09_20_000002_add_certificate_threshold_to_events.php app/Models/Certificate.php app/Models/CertificateVerification.php app/Models/Event.php app/Models/User.php app/Models/Registration.php database/factories/CertificateFactory.php tests/Feature/CertificateModelRelationTest.php app/Services/MembershipService.php database/seeders/PermissionSeeder.php composer.json composer.lock
git commit -m "feat: certificate schema + models + permissions

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 2: CertificateService (eligibility + batch + revoke) + AssignmentService::complete()

**Files:**
- Create: `app/Services/CertificateService.php`
- Create: `tests/Feature/CertificateServiceTest.php`
- Modify: `app/Services/AssignmentService.php:71-74` (tambah `complete()` setelah `confirm()`, memakai `terapkanStatus` yang ada)

**Interfaces:**
- Consumes: `Assignment::ACTIVE`, `Assignment::TRANSITIONS` (edge `→ completed` sudah ada, tinggal dikendarai), `Attendance` (`status` present/late, `user_id`, `event_id`), `EventShift` (soft delete — cek `SoftDeletes` di model; bila ada pakai `whereNull('deleted_at')`), `Certificate` + `CertificateVerification` (Task 1), `AuditLogService::record($actor, $action, $class, $id, $ctx)` (pola `AttendanceService`: ctx berisi `organization_id` + `event_id`).
- Produces: `CertificateService::{isEligible(Event, User): bool, effectiveThreshold(Event): int, issueBatch(Event, User, ?int): array{issued: array<Certificate>, skipped: int}, issueIndividual(Event, User, User): Certificate, revoke(Certificate, User, string): Certificate}`, `AssignmentService::complete(Assignment, User): Assignment`.

- [ ] **Step 1: Tambah `AssignmentService::complete()` + failing test**

```php
public function complete(Assignment $assignment, User $actor): Assignment
{
    return DB::transaction(function () use ($assignment, $actor): Assignment {
        $assignment = $assignment->refresh();
        abort_unless($assignment->isActive(), 422, 'Hanya assignment aktif yang bisa diselesaikan.');
        $shift = $assignment->shift()->firstOrFail();
        abort_if(now()->lt($shift->end_at), 422, 'Shift belum selesai.');

        return $this->terapkanStatus($assignment, 'completed', $actor, 'Evaluasi pasca-event selesai.');
    });
}
```

Test (di `CertificateServiceTest.php`, prefix `sertTes`):

```php
it('sertTesCompleteMenutupAktifShiftLewat', function (): void {
    // assignment confirmed + shift end_at kemarin → complete() → completed + 1 history
});

it('sertTesCompleteTolakShiftBelumLewat', function (): void {
    // shift end_at besok → 422 'Shift belum selesai.'
});
```

Run test → FAIL (method belum ada) → tulis `complete()` → PASS.

- [ ] **Step 2: Tulis `CertificateService` (lengkap, tanpa placeholder)**

```php
<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentHistory;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateService
{
    public const DEFAULT_THRESHOLD = 50;

    public function __construct(private AuditLogService $audit, private AssignmentService $assignments) {}

    public function effectiveThreshold(Event $event): int
    {
        return $event->certificate_min_attendance_pct ?? self::DEFAULT_THRESHOLD;
    }

    public function isEligible(Event $event, User $user): bool
    {
        if (! in_array($event->status, ['completed', 'archived'], true)) {
            return false;
        }
        $penyebut = Assignment::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', Assignment::ACTIVE)
            ->whereHas('shift', fn ($q) => $q->whereNull('deleted_at'))
            ->count();
        if ($penyebut === 0) {
            return false;
        }
        $hadir = Attendance::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['present', 'late'])
            ->count();
        if ($hadir < 1) {
            return false;
        }

        return $hadir / $penyebut * 100 >= $this->effectiveThreshold($event);
    }

    /** @return array{issued: array<Certificate>, skipped: int} */
    public function issueBatch(Event $event, User $actor, ?int $ambang = null): array
    {
        abort_unless(in_array($event->status, ['completed', 'archived'], true), 422, 'Sertifikat hanya diterbitkan untuk event yang sudah selesai.');

        return DB::transaction(function () use ($event, $actor, $ambang): array {
            $terkunci = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($ambang !== null) {
                abort_unless($ambang >= 1 && $ambang <= 100, 422, 'Ambang kehadiran harus 1–100.');
                $terkunci->forceFill(['certificate_min_attendance_pct' => $ambang])->save();
            }
            // tutup assignment aktif yang shift-nya lewat
            $tugas = Assignment::where('event_id', $terkunci->id)
                ->whereIn('status', Assignment::ACTIVE)
                ->with('shift')
                ->get();
            foreach ($tugas as $item) {
                if ($item->shift !== null && now()->gte($item->shift->end_at)) {
                    $this->assignments->complete($item, $actor);
                }
            }
            // terbitkan untuk yang layak
            $layakIds = Assignment::where('event_id', $terkunci->id)
                ->whereIn('status', ['completed'])
                ->distinct()
                ->pluck('user_id');
            $diterbitkan = [];
            $dilewati = 0;
            foreach ($layakIds as $userId) {
                $user = User::whereKey($userId)->firstOrFail();
                if (! $this->isEligible($terkunci->refresh(), $user)) {
                    $dilewati++;
                    continue;
                }
                if (Certificate::where('event_id', $terkunci->id)->where('user_id', $userId)->exists()) {
                    $dilewati++;
                    continue;
                }
                // sertifikat dicabut sebelumnya → hormati, jangan terbitkan ulang
                $pernahCabut = Certificate::where('event_id', $terkunci->id)->where('user_id', $userId)->whereNotNull('revoked_at')->exists();
                if ($pernahCabut) {
                    $dilewati++;
                    continue;
                }
                $diterbitkan[] = $this->terbitkan($terkunci, $user, $actor);
            }

            return ['issued' => $diterbitkan, 'skipped' => $dilewati];
        });
    }

    public function issueIndividual(Event $event, User $target, User $actor): Certificate
    {
        abort_unless(in_array($event->status, ['completed', 'archived'], true), 422, 'Sertifikat hanya diterbitkan untuk event yang sudah selesai.');
        abort_unless($this->isEligible($event, $target), 422, 'Volunteer belum memenuhi syarat sertifikat.');

        return DB::transaction(function () use ($event, $target, $actor): Certificate {
            abort_if(Certificate::where('event_id', $event->id)->where('user_id', $target->id)->whereNull('revoked_at')->exists(), 422, 'Sertifikat sudah diterbitkan.');
            // bila yang ada hanya baris dicabut → koreksi salah-cabut: terbitkan baru
            return $this->terbitkan($event, $target, $actor);
        });
    }

    public function revoke(Certificate $sertifikat, User $actor, string $alasan): Certificate
    {
        return DB::transaction(function () use ($sertifikat, $actor, $alasan): Certificate {
            abort_if($sertifikat->isRevoked(), 422, 'Sertifikat sudah dicabut.');
            abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pencabutan minimal 10 karakter.');
            $sertifikat->forceFill(['revoked_at' => now(), 'revoke_reason' => $alasan])->save();
            $event = $sertifikat->event()->firstOrFail();
            $this->audit->record($actor, 'certificate.revoked', Certificate::class, $sertifikat->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'new' => ['reason' => $alasan],
            ]);

            return $sertifikat->refresh();
        });
    }

    private function terbitkan(Event $event, User $target, User $actor): Certificate
    {
        $registrationId = $target->registrations()->where('event_id', $event->id)->where('status', 'accepted')->value('id');
        abort_if($registrationId === null, 422, 'Tidak ada pendaftaran diterima untuk volunteer ini.');
        $mentah = Str::random(64);
        $sertifikat = Certificate::unguarded(fn (): Certificate => Certificate::create([
            'event_id' => $event->id,
            'user_id' => $target->id,
            'registration_id' => $registrationId,
            'certificate_no' => $this->nomorBaru(),
            'issued_at' => now(),
            'qr_token_hash' => hash('sha256', $mentah),
        ]));
        $this->audit->record($actor, 'certificate.issued', Certificate::class, $sertifikat->id, [
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
        ]);

        return $sertifikat->refresh();
    }

    private function nomorBaru(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $nomor = 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(6));
            if (! Certificate::where('certificate_no', $nomor)->exists()) {
                return $nomor;
            }
        }

        return 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(8));
    }
}
```

Catatan untuk implementer: `User::registrations()` — verifikasi nama relasi di `User.php` sebelum pakai; bila bernama lain (mis. `registration`), sesuaikan. `Str::random(6)` alnum (huruf+angka, tanpa simbol) — uppercase-kan; pastikan tidak mengandung `-` agar format stabil. Percabangan `issueIndividual` saat baris dicabut ada: unique `(event_id,user_id)` melarang dua baris — maka koreksi salah-cabut = revoke baris lama DIIZINKAN terbit baru? TIDAK: unique constraint menolak. Koreksi yang benar: `issueIndividual` melempar 422 bila ADA baris apa pun (aktif maupun dicabut); salah-cabut dikoreksi dengan menerbitkan nomor baru? Itu pun melanggar unique. Keputusan: salah-cabut TIDAK bisa dikoreksi via terbit baru (unique melarang); spec §3 "terbitkan manual per individu via endpoint yang sama dengan flag" direvisi di sini menjadi: endpoint individual menolak bila baris (aktif/dicabut) sudah ada — organizer harus sadar revoke bersifat final. Tulis test yang mengunci perilaku ini (`sertTesIndividualTolakBilaPernahAda`). Bila menemukan konflik dengan spec, catat di report sebagai deviasi eksplisit.

- [ ] **Step 3: Tulis test eligibility + batch + revoke (TDD penuh)**

```php
sertTesTidakLayakEventBelumSelesai      // event ongoing + hadir penuh → isEligible false
sertTesTidakLayakTanpaAssignmentAktif   // hadir tanpa assignment aktif → false
sertTesTidakLayakDiBawahAmbang          // 1 hadir / 3 assignment aktif (33% < 50) → false
sertTesLayakTepatAmbang                 // 1 hadir / 2 aktif (50%) → true
sertTesAmbangPerEventDihormati          // pct=100: 1/2 → false; 2/2 → true
sertTesShiftSoftDeleteTakMasukPenyebut  // assignment shift terhapus → penyebut berkurang
sertTesCancelledTakMasukPenyebut        // assignment cancelled → tak dihitung
sertTesBatchTerbitDanTutup              // 2 layak + 1 tak layak → issued 2, skipped 1; assignment layak → completed + history; audit certificate.issued tercatat
sertTesBatchShiftBelumLewatTakDitutup   // shift end_at besok → tetap aktif, volunteer tak layak (0 hadir? atau hadir manual?) → skipped; assignment TIDAK completed
sertTesBatchRerunIdempoten              // issueBatch kedua → issued 0, skipped N, total baris tetap
sertTesBatchTolakEventOngoing           // 422
sertTesBatchSimpanAmbang                // ambang=100 → pct tersimpan + diterapkan
sertTesRevokeDanHormati                 // revoke → isRevoked; issueBatch berikutnya → skipped (tidak terbit ulang); assignment tetap completed
sertTesRevokeAlasanPendek               // alasan 9 karakter → 422
sertTesRevokeGanda                      // revoke kedua → 422
sertTesIndividualTolakBilaPernahAda     // sudah ada (aktif/dicabut) → 422 (kunci finalitas revoke)
sertTesCompleteMenutupAktifShiftLewat + sertTesCompleteTolakShiftBelumLewat (Step 1)
```

Helper: buat paket event completed + assignment confirmed + attendance present/late via factory + service (jangan tulis mentah kecuali perlu). Attendance: buat via `Attendance::unguarded(create)` dengan `idempotency_key` UUID unik per baris (kolom unique). Event completed: transisikan via `EventService::transitionTo` berurutan (draft→published→registration_open→registration_closed→ongoing→completed) atau `forceFill` status bila transisi terlalu panjang — pilih yang cepat tapi catat di report.

Run: `docker compose exec app php artisan test tests/Feature/CertificateServiceTest.php` → FAIL (service belum ada) → tulis service → PASS semua (±18 test).

- [ ] **Step 4: Verifikasi + commit**

Run: affected suite hijau; `pint --test` + `phpstan level 5` pada `app/Services/CertificateService.php` + `app/Services/AssignmentService.php`.

```bash
git status --porcelain
git add app/Services/CertificateService.php app/Services/AssignmentService.php tests/Feature/CertificateServiceTest.php
git commit -m "feat: certificate service + eligibility + batch + revoke

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 3: Controller organizer + Form Request + policy + route + view

**Files:**
- Create: `app/Http/Requests/IssueCertificatesRequest.php`
- Create: `app/Http/Requests/RevokeCertificateRequest.php`
- Create: `app/Policies/CertificatePolicy.php`
- Create: `app/Http/Controllers/Organizer/CertificateController.php`
- Create: `resources/views/organizer/events/certificates/index.blade.php`
- Create: `resources/views/organizer/events/certificates/show.blade.php`
- Create: `tests/Feature/OrganizerCertificateTest.php`
- Modify: `routes/web.php:210-222` (tambah group `certificates` setelah `announcements`, pola identik), `app/Providers/AppServiceProvider.php:73-84` (Gate::policy + Route::bind `certificate` event-scoped, pola `announcement`)

**Interfaces:**
- Consumes: `CertificateService::{issueBatch, revoke, issueIndividual, effectiveThreshold}` (Task 2); pola `PublishAnnouncementRequest::authorize` (sample-then-fallback); pola `OrganizerAnnouncementController` (baca file itu dulu — struktur index/show/publish).
- Produces: route `organizer.events.certificates.{index,issue,show,revoke}`; view menerima `org, event, certificates (paginate 15), diterbitkan, dilewati, ambang`.

- [ ] **Step 1: Baca controller announcement organizer sebagai pola, tulis policy + requests**

Baca `app/Http/Controllers/Organizer/AnnouncementController.php` sekali (jangan tiru buta — adaptasi ke certificate).

```php
// app/Policies/CertificatePolicy.php — pola AnnouncementPolicy identik
viewAny(User $user, Event $event): member + certificate.read
view(User $user, Certificate $certificate): member + certificate.read
issue(User $user, Certificate|Event $subject): member + certificate.issue
revoke(User $user, Certificate $certificate): member + certificate.revoke
```

```php
// IssueCertificatesRequest: authorize = sample-then-fallback (pola PublishAnnouncementRequest: bila ada certificate di event → can('issue', sample), else member + certificate.issue)
// rules: ['min_attendance_pct' => ['nullable', 'integer', 'min:1', 'max:100']]
// messages Indonesia: min_attendance_pct.min/max → 'Ambang kehadiran harus 1–100.'

// RevokeCertificateRequest: authorize = route certificate instanceof → can('revoke', certificate)
// rules: ['reason' => ['required', 'string', 'min:10', 'max:2000']]
// messages: reason.required → 'Alasan pencabutan wajib diisi.'; reason.min → 'Alasan pencabutan minimal 10 karakter.'
```

- [ ] **Step 2: Tulis controller + route + binding + view (failing test dulu)**

Failing test (di `OrganizerCertificateTest.php`):

```php
sertTesOrganizerTerbitBatch
// owner POST issue (event completed, 2 layak + 1 tak layak, session password confirm)
// → redirect index + flash '2 sertifikat diterbitkan, 1 dilewati.'
// + 2 baris certificates + assignment layak → completed
```

Controller:

```php
public function index(Organization $organization, Event $event): View  // Gate viewAny; paginate 15; kirim ambang efektif
public function show(...)  // Gate view; load user + verifications terbaru dulu
public function issue(IssueCertificatesRequest $request, ...): RedirectResponse
// $valid = validated; [$diterbitkan, $dilewati] = service->issueBatch($event, user, $valid['min_attendance_pct'] ?? null)
// try/catch HttpException: 404 → throw; else back()->withErrors(['threshold' => msg])
// redirect index + flash Indonesia: "{$n} sertifikat diterbitkan, {$m} dilewati."
public function revoke(RevokeCertificateRequest $request, ...): RedirectResponse
// service->revoke($certificate, user, $valid['reason']); flash 'Sertifikat {no} dicabut.'
```

Route (setelah group announcements, sebelum penutup `{event}`):

```php
Route::prefix('certificates')->name('certificates.')->group(function () {
    Route::get('/', [OrganizerCertificateController::class, 'index'])->name('index');
    Route::post('issue', [OrganizerCertificateController::class, 'issue'])
        ->middleware(['password.confirm', 'throttle:10,1'])
        ->name('issue');
    Route::prefix('{certificate}')->group(function () {
        Route::get('/', [OrganizerCertificateController::class, 'show'])->name('show');
        Route::post('revoke', [OrganizerCertificateController::class, 'revoke'])
            ->middleware('password.confirm')
            ->name('revoke');
    });
});
```

Binding `certificate` event-scoped (pola `announcement`). View index: tabel nomor + volunteer + issued_at + status + form issue (input ambang opsional + tombol, `@can('issue', ...)`). View show: detail + riwayat verifikasi (read-only list) + form revoke (`@can('revoke', ...)`).

Run test → FAIL (route/controller belum ada) → tulis semua → PASS.

- [ ] **Step 3: Tambah test staf-403 + lintas-404 + validasi**

```php
sertTesStafReadOnly403Mutasi       // staf read-only: GET index OK; POST issue → 403; POST revoke → 403
sertTesLintasEvent404              // certificate event B via URL event A → 404
sertTesAmbangInvalid422            // min_attendance_pct 0 / 101 → 422 (redirect back + errors)
sertTesRevokeAlasanPendek422       // reason 9 karakter → errors
sertTesIssueEventOngoingDitolak    // event ongoing → errors (service 422 → withErrors, bukan 500)
```

Run: `docker compose exec app php artisan test tests/Feature/OrganizerCertificateTest.php` → PASS (±6 test).

- [ ] **Step 4: Verifikasi + commit**

Run: affected suite; `pint --test` + `phpstan level 5` file app tersentuh.

```bash
git status --porcelain
git add app/Http/Requests/IssueCertificatesRequest.php app/Http/Requests/RevokeCertificateRequest.php app/Policies/CertificatePolicy.php app/Http/Controllers/Organizer/CertificateController.php resources/views/organizer/events/certificates/index.blade.php resources/views/organizer/events/certificates/show.blade.php tests/Feature/OrganizerCertificateTest.php routes/web.php app/Providers/AppServiceProvider.php
git commit -m "feat: organizer certificate issue + revoke + scoped binding

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 4: Unduhan volunteer (PDF) + verifikasi publik + daftar

**Files:**
- Create: `app/Http/Controllers/Volunteer/CertificateController.php`
- Create: `app/Http/Controllers/PublicCertificateController.php`
- Create: `resources/views/volunteer/certificates/index.blade.php`
- Create: `resources/views/certificates/pdf.blade.php` (template dompdf A4 landscape)
- Create: `resources/views/certificates/verify.blade.php` (halaman publik + `noindex`)
- Create: `tests/Feature/CertificateDownloadVerifyTest.php`
- Modify: `routes/web.php:88-93` (tambah `my/certificates` + `my/certificates/{certificateVol}/download` setelah notifications; tambah `verify/certificate/{token}` publik dekat `events` publik baris 32-37), `app/Providers/AppServiceProvider.php` (binding `certificateVol` own-only `user_id = auth()->id()`, pola `assignmentVol`)

**Interfaces:**
- Consumes: `Certificate::{isRevoked}`, `CertificateVerification` (Task 1); hasil Step 1 Task 1 (class QR yang tersedia — `BaconQrCode\Writer` atau fallback yang dilaporkan); pola `VolunteerAnnouncementController`/`VolunteerScheduleController` (baca salah satu dulu untuk struktur own-only).
- Produces: route `my.certificates.{index,download}` + `certificates.verify`; PDF stream (bukan file tersimpan); baris verification per kunjungan valid.

- [ ] **Step 1: Tulis controller volunteer + publik + route + binding (failing test dulu)**

```php
sertTesVolunteerLihatDaftarSendiri
// 2 sertifikat milik + 1 milik orang lain (event sama) → index hanya tampilkan 2 milik

sertTesUnduhPdfMilikSendiri
// GET download milik → 200 + Content-Type application/pdf + body diawali %PDF

sertTesUnduhMilikOrang404
// download id milik orang → 404

sertTesUnduhDicabut422
// revoke lalu download → 422 + pesan 'Sertifikat ini telah dicabut.'

sertTesVerifikasiPublikValid
// GET /verify/certificate/{token-mentah? TIDAK — token mentah tidak tersimpan!}
```

Keputusan token verifikasi (PENTING, spec §2 menyimpan hanya `qr_token_hash`): token mentah hanya diketahui saat generate (tak tersimpan). Maka URL verifikasi memakai `certificate_no` (`WV-2026-XXXXXX`) sebagai identifier publik — cukup unik + tak berurutan (acak 6 alnum, ~2 miliar kombinasi; brute force tak feasible + throttle 60/menit). QR di PDF meng-encode URL penuh `route('certificates.verify', $certificate_no)`. Lookup: `where('certificate_no', $token)`. Bila menemukan konflik keamanan dengan spec ("token acak 64 hex sebagai capability"), catat di report sebagai deviasi eksplisit — `qr_token_hash` tetap ditulis (kolom ada, terisi hash acak) tapi verifikasi lookup via `certificate_no`. Test memakai `$sertifikat->certificate_no`.

```php
sertTesVerifikasiPublikValid     // → 200 + tampil nomor + nama + VALID + 1 baris verification
sertTesVerifikasiDicabut         // → 200 + DICABUT (bukan 404)
sertTesVerifikasiAsing404TanpaLog // token asing → 404 + 0 baris verification baru
sertTesVerifikasiNoindex          // body mengandung noindex
```

Controller volunteer: `index` (own `user_id`, paginate 12) + `download` (binding own-only; `abort_if(isRevoked, 422, ...)`; render `certificates.pdf` via `Pdf::loadView(...)->setPaper('a4','landscape')->stream()` — facade `Barryvdh\DomPDF\Facade\Pdf`). Controller publik: lookup `certificate_no` → 404 bila tak ada; tulis verification (ip + user_agent ≤512 char); return view verify. Route publik: `Route::get('verify/certificate/{nomor}', ...)->middleware('throttle:60,1')->name('certificates.verify')`.

Run test → FAIL → tulis semua → PASS (±8 test).

- [ ] **Step 2: Tulis template PDF + halaman verifikasi (konten nyata)**

`pdf.blade.php`: HTML mandiri (inline CSS — dompdf tak baca asset Vite): kop org/event, "SERTIFIKAT PENGHARGAAN", nama volunteer (besar), teks "telah berpartisipasi sebagai relawan pada {event} ({tanggal})", nomor sertifikat, tanggal terbit, QR inline base64 (`<img src="data:image/png;base64,...">` — PNG dari BaconQrCode Writer atau library yang tersedia Task 1; ukuran 150px), tanda tangan block (nama organizer — `auth()->user()->name` TIDAK; pakai nama owner org? Sederhana: baris "Panitia, {org name}" + tanggal — tanpa nama orang fiktif). `verify.blade.php`: layout app (x-app-layout boleh? halaman publik tanpa auth — pakai layout guest minimal: HTML mandiri sederhana + `noindex`), tampilkan nomor/nama/event/tanggal + badge VALID (hijau) / DICABUT (merah) + pesan pencabutan (tanpa alasan internal — hanya status).

- [ ] **Step 3: Verifikasi + commit**

Run: affected suite; `pint --test` + `phpstan level 5` file app tersentuh. Peringatan dompdf: pastikan tidak ada CSS eksternal/JS di template PDF (dompdf gagal diam-diam → test `%PDF` menangkapnya).

```bash
git status --porcelain
git add app/Http/Controllers/Volunteer/CertificateController.php app/Http/Controllers/PublicCertificateController.php resources/views/volunteer/certificates/index.blade.php resources/views/certificates/pdf.blade.php resources/views/certificates/verify.blade.php tests/Feature/CertificateDownloadVerifyTest.php routes/web.php app/Providers/AppServiceProvider.php
git commit -m "feat: volunteer certificate download + public verification

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 5: Otorisasi + isolasi + konkurensi + full gates

**Files:**
- Create: `tests/Feature/CertificateAuthorizationTest.php`
- Create: `tests/Feature/CertificateIsolationTest.php`
- Create: `tests/Feature/CertificateConcurrencyTest.php`
- Modify: `README.md` (satu baris status Phase 5A — cari baris status phase yang ada)

**Interfaces:**
- Consumes: seluruh route Task 3–4; pola `OperationsAuthorizationTest` (helper paket `opsJadwalPaket` TIDAK reusable lintas file — tulis helper sendiri prefix `sert`; baca `OperationsAuthorizationTest.php` + `RegistrationIsolationTest.php` + `ShiftQuotaConcurrencyTest.php` sebagai pola).
- Produces: matriks hijau + full suite hijau + README satu baris.

- [ ] **Step 1: Authorization matrix (gaya OperationsAuthorizationTest)**

```php
sertTesGuestDiarahkanKeLogin   // semua route certificate organizer + volunteer + issue/revoke → redirect login (verify publik tetap 200/404 tanpa login)
sertTesStafReadOnly403SemuaMutasi // staf read-only: index/show OK; issue → 403; revoke → 403
sertTesLintasOrgEvent404        // org B akses certificate org A → 404 (index/show/issue/revoke/download)
sertTesJadwalMilikSendiri → sertTesSertifikatMilikSendiri // volunteer: milik OK; milik orang → 404 (index tak tampil, download 404)
sertTesOwnerMengelolaSertifikat // owner: issue + revoke + show OK
sertTesAdminTanpaRuteCertificate // Route::has('admin.certificates.*') false — non-scope dikunci, bukan dibangun
```

Run → PASS (±6 test). Kegagalan di sini = bug Task 3/4 → perbaiki di file Task 3/4 (commit terpisah `fix:` bila perlu, catat di report).

- [ ] **Step 2: Isolation simetris (gaya RegistrationIsolationTest)**

```php
sertTesIsolasiDuaOrgSimetris
// Org A + Org B masing-masing: event completed + assignment + attendance + certificate
// A tak bisa read/download/revoke milik B (404) dan sebaliknya; verifikasi publik nomor B tetap VALID (publik bukan tenant)
```

Run → PASS.

- [ ] **Step 3: Konkurensi (pola ShiftQuotaConcurrencyTest)**

```php
sertTesBatchGandaIdempoten // issueBatch 2x paralel (fork bila pcntl tersedia, else skip by design + residual sekuensial): tepat 1 baris per (event,user); certificate_no unik; pola file ShiftQuotaConcurrencyTest (baca dulu)
```

Run → PASS/skip-by-design.

- [ ] **Step 4: Full gates + README + commit**

Run: `docker compose exec app php artisan test` (penuh, ekspektasi hijau; 2 skip lama + skip konkurensi bila pcntl tak ada),
`docker compose exec app ./vendor/bin/pint --test` (seluruh repo — ekspektasi PASS; bila menyentuh file lama, JANGAN commit perubahannya: laporkan saja),
`docker compose exec app ./vendor/bin/phpstan analyse --level=5 --no-progress` (ekspektasi No errors),
`docker compose exec app composer audit` (bersih).
README: satu baris status Phase 5A (pola baris Phase 4 yang ada — baca dulu, tiru format).

```bash
git status --porcelain
git add tests/Feature/CertificateAuthorizationTest.php tests/Feature/CertificateIsolationTest.php tests/Feature/CertificateConcurrencyTest.php README.md
git commit -m "feat: certificate authorization + isolation + concurrency gates

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:** §1 service+complete+permission → T1 (perm) + T2 (service+complete); §2 tabel+ambang+nomor → T1; §3 route organizer/volunteer/publik/admin+batch+revoke → T2 (logika) + T3 (organizer) + T4 (volunteer+publik) + T5 (admin non-scope dikunci); §4 PDF → T1 (dependensi) + T4; §5 TDD+gates → T5. `issueIndividual` di service (T2) tapi tanpa endpoint — sesuai spec (hanya dipakai koreksi; endpoint menyusul bila dibutuhkan; test mengunci 422-nya). Job queue ≥50 penerima: spec menyebut pola, tapi service `issueBatch` sinkron — KEPUTUSAN: threshold 50 + job dispatch TIDAK diimplementasi di plan ini (YAGNI: batch sertifikat ≤ ratusan baris insert ringan, jauh di bawah broadcast notifikasi; HTTP menunggu masih wajar). Bila reviewer mengangkat, adjudikasi: terima sebagai deviasi sadar, catat di ledger.

**2. Placeholder scan:** tidak ada TBD/TODO/"nanti"/"secukupnya"; semua langkah berisi kode/aturan/file konkret. Library QR: Task 1 Step 1 memaksa keputusan biner (cek `class_exists` → require bila perlu) + report ke Task 4 — bukan placeholder.

**3. Type consistency:** `issueBatch(Event, User, ?int): array{issued, skipped}` konsisten T2→T3; `AssignmentService::complete(Assignment, User): Assignment` konsisten T2 internal; `certificate_no` string(32) vs format `WV-YYYY-XXXXXX` (14 char — muat); `qr_token_hash` string(64) = sha256 hex (64 char — pas); route names `organizer.events.certificates.*` / `my.certificates.*` / `certificates.verify` konsisten T3→T4→T5; helper test prefix `sertTes` + file `sert*` konsisten antar task.
