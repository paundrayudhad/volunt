# Phase 5C Artist + Liaison Officer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun manajemen artis/penampil per event + LO (assignment volunteer→artis), status alur & kehadiran independen, rider, catatan lapangan, dan validasi konflik otomatis.

**Architecture:** Satu domain baru menempel pada `Event`: model `Artist` + `ArtistLiaison` + `ArtistStatusHistory` + `ArtistNote`, satu-satunya penulis `ArtistService`, controller organizer + volunteer-LO tipis via Form Request, policy Spatie + membership (pola Phase 5B `IncidentService`).

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, Spatie permission, Pest, Blade.

**Spec:** `docs/superpowers/specs/2026-09-22-phase5c-artist-liaison-design.md`

## Global Constraints

- Docker via `docker compose exec app` untuk semua perintah artisan/composer/pest.
- Full suite via biner langsung dengan memory 1G: `php -d memory_limit=1G ./vendor/bin/pest` (flag `-d` tidak diteruskan ke worker Pest bila via `artisan test`; OOM 128M pra-ada di helper `daftarTesPdf`).
- Gates per task: Pest hijau (file task + regresi terkait), Pint (`./vendor/bin/pint --test` lalu tanpa flag bila perlu), Larastan level 5 (`./vendor/bin/phpstan analyse --no-progress`), `composer audit` bersih di akhir.
- Prose Indonesia + identifier English; pesan validasi/error Indonesia; konten nyata tanpa placeholder.
- Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model; business logic hanya di service.
- `$fillable` allowlist kosong/tidak mencakup kolom sensitif (`event_id`, `*_id` relasi, `rider_fulfilled` tidak fillable via update umum); tulis via `Model::unguarded()` / `forceFill()` di service.
- Check constraints via `DB::statement` (pola 5B, bukan enum native).
- `git add` eksplisit per file; commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`.
- Helper test prefix per file (pola `daftarTesPdf` di VolunteerRegistrationTest).
- Binding scoped org → event → artist; luar scope → 404; terlihat tapi tak diizinkan → 403.
- Throttle store/transition 30/menit; tanpa `password.confirm` (soft-delete, konsisten 5B).
- Paginasi index 15; flash Indonesia.

## File Map

**Buat baru:**
- `database/migrations/2026_09_22_000001_create_artists_tables.php` — `artists`, `artist_liaisons`, `artist_status_histories`, `artist_notes` + check constraints + index.
- `database/migrations/2026_09_22_000002_backfill_artist_permissions.php` — backfill `artist.manage` (owner aktif) + `artist.liaise` (volunteer accepted); down() cabut selektif.
- `app/Models/Artist.php`, `app/Models/ArtistLiaison.php`, `app/Models/ArtistStatusHistory.php`, `app/Models/ArtistNote.php` (+ factory `database/factories/ArtistFactory.php` bila pola 5B memakai factory — cek `database/factories/IncidentFactory.php`).
- `app/Services/ArtistService.php` — satu-satunya penulis keempat tabel.
- `app/Policies/ArtistPolicy.php` — `viewAny`/`view`/`manage` + `liaise(artist)` own-scoped.
- Form Requests: `StoreArtistRequest`, `UpdateArtistRequest`, `TransitionArtistRequest`, `AssignLiaisonRequest`, `StoreArtistNoteRequest`, `ToggleRiderRequest`, `LiaisonStatusRequest` (LO update status+kehadiran).
- `app/Http/Controllers/OrganizerArtistController.php` (index/show/store/update/transition/assign/release/destroy) + `app/Http/Controllers/VolunteerLiaisonController.php` (index/status/notes/rider).
- Blade: `resources/views/organizer/artists/{index,show,partials}.blade.php` + `resources/views/my/liaison/{index,show}.blade.php` + tombol/link dari show event organizer.
- Test: `tests/Feature/ArtistStatusTest.php`, `tests/Feature/ArtistConflictTest.php`, `tests/Feature/ArtistLiaisonTest.php`, `tests/Feature/ArtistAuthorizationTest.php`, `tests/Feature/ArtistIsolationTest.php`, `tests/Feature/BackfillArtistPermissionsTest.php`.

**Ubah:**
- `database/seeders/PermissionSeeder.php` — tambah `artist.manage`, `artist.liaise`, `artist.read` (~baris 44-46, deretan incident/lostfound).
- `app/Models/Event.php` — relasi `artists(): HasMany`.
- `app/Models/User.php` — relasi `artistLiaisons()` bila pola 5B menambah relasi reporter (cek dulu; bila tidak ada, lewati).
- `app/Services/MembershipService.php` — sinkronisasi permission owner baru (cek pola `incident.manage` di file ini; ikuti exactly).
- `routes/web.php` — group organizer `artists` + group volunteer `my/liaison` (ikuti blok incidents/lost-found ~baris 269-292 + my/* ~baris 110-120).

---

### Task 1: Migrasi + Model + Permission dasar

**Files:**
- Create: `database/migrations/2026_09_22_000001_create_artists_tables.php`
- Create: `app/Models/Artist.php`
- Create: `app/Models/ArtistLiaison.php`
- Create: `app/Models/ArtistStatusHistory.php`
- Create: `app/Models/ArtistNote.php`
- Modify: `database/seeders/PermissionSeeder.php` (~baris 44-46)
- Modify: `app/Models/Event.php` (tambah relasi)

**Interfaces:**
- Consumes: `events.start_date/end_date` (cek kolom di migrasi `2026_09_17_000001_create_events_table.php` — plan ini mengasumsikan `start_date`, `end_date`; bila namanya berbeda, pakai nama aktual dan catat di laporan).
- Produces: `Artist::STATUSES`, `Artist::NEXT`, `Artist::ATTENDANCES`, relasi `event()`, `liaisons()` (aktif saja: `->whereNull('artist_liaisons.deleted_at')`), `histories()`, `notes()`; `ArtistLiaison` SoftDeletes; history/note tanpa timestamps-update (history hanya `created_at`, note `created_at` saja — ikuti `incident_status_histories`: `$table->timestamp('created_at')->useCurrent()`, tanpa `updated_at`).

- [ ] **Step 1: Tulis migrasi**

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
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('genre', 100)->nullable();
            $table->string('stage', 100)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('performance_order')->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('rider_text')->nullable();
            $table->boolean('rider_fulfilled')->default(false);
            $table->string('status', 20)->default('scheduled');
            $table->string('attendance', 20)->default('expected');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'scheduled_at']);
            $table->index(['event_id', 'status']);
        });
        DB::statement("ALTER TABLE artists ADD CONSTRAINT artists_status_check CHECK (status IN ('scheduled','soundcheck','performing','done','cancelled'))");
        DB::statement("ALTER TABLE artists ADD CONSTRAINT artists_attendance_check CHECK (attendance IN ('expected','arrived','no_show'))");

        Schema::create('artist_liaisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
        });
        DB::statement('CREATE UNIQUE INDEX artist_liaisons_aktif_unique ON artist_liaisons (artist_id, user_id) WHERE deleted_at IS NULL');

        Schema::create('artist_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('from_attendance', 20)->nullable();
            $table->string('to_attendance', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('artist_id');
        });

        Schema::create('artist_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index('artist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_notes');
        Schema::dropIfExists('artist_status_histories');
        Schema::dropIfExists('artist_liaisons');
        Schema::dropIfExists('artists');
    }
};
```

- [ ] **Step 2: Tulis model `Artist`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['scheduled', 'soundcheck', 'performing', 'done', 'cancelled'];

    public const ATTENDANCES = ['expected', 'arrived', 'no_show'];

    /** @var array<string, array<int, string>> */
    public const NEXT = [
        'scheduled' => ['soundcheck'],
        'soundcheck' => ['performing'],
        'performing' => ['done'],
        'done' => [],
        'cancelled' => [],
    ];

    public const DEFAULT_DURATION = 60;

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'rider_fulfilled' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<ArtistLiaison, $this> */
    public function liaisons(): HasMany
    {
        return $this->hasMany(ArtistLiaison::class)->whereNull('artist_liaisons.deleted_at');
    }

    /** @return HasMany<ArtistStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ArtistStatusHistory::class);
    }

    /** @return HasMany<ArtistNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ArtistNote::class);
    }

    public function endsAt(): ?\Carbon\CarbonInterface
    {
        if ($this->scheduled_at === null) {
            return null;
        }

        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?? self::DEFAULT_DURATION);
    }
}
```

Model pendamping (3 file, pola `IncidentStatusHistory`: `$fillable = []`, casts `created_at => datetime`, relasi balik):

```php
// ArtistLiaison
class ArtistLiaison extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [];
    /** @var array<string, string> */
    protected $casts = ['deleted_at' => 'datetime'];
    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

// ArtistStatusHistory
class ArtistStatusHistory extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [];
    /** @var array<string, string> */
    protected $casts = ['created_at' => 'datetime'];
    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}

// ArtistNote
class ArtistNote extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [];
    /** @var array<string, string> */
    protected $casts = ['created_at' => 'datetime'];
    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
```

- [ ] **Step 3: Tambah permission ke seeder + relasi Event**

Di `PermissionSeeder.php` deretan 5B, tambah:
```php
'artist.manage',
'artist.liaise',
'artist.read',
```
Di `Event.php`, tambah:
```php
/** @return HasMany<Artist, $this> */
public function artists(): HasMany
{
    return $this->hasMany(Artist::class);
}
```

- [ ] **Step 4: Jalankan migrasi + seeder di Docker, pastikan hijau**

Run: `docker compose exec app php artisan migrate --force`
Expected: OK, 1 migrasi baru (tabel `artists`, `artist_liaisons`, `artist_status_histories`, `artist_notes`).

Run: `docker compose exec app php artisan db:seed --class=PermissionSeeder --force`
Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_22_000001_create_artists_tables.php app/Models/Artist.php app/Models/ArtistLiaison.php app/Models/ArtistStatusHistory.php app/Models/ArtistNote.php database/seeders/PermissionSeeder.php app/Models/Event.php
git commit -m "feat: migrasi + model artist dan liaison

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 2: ArtistService inti (CRUD + status ganda + cancel)

**Files:**
- Create: `app/Services/ArtistService.php`
- Test: `tests/Feature/ArtistStatusTest.php`

**Interfaces:**
- Consumes: model Task 1 (`Artist::STATUSES/NEXT/ATTENDANCES`, `endsAt()`); `AuditLogService::record(User $actor, string $aksi, string $model, int $id, array $konteks)` (cek signature aktual di `app/Services/AuditLogService.php` sebelum pakai — pola 5B: `$this->audit->record($pelapor, 'incident.reported', Incident::class, $insiden->id, [...])`).
- Produces: `create(Event, User, array): Artist`, `update(Artist, User, array): Artist`, `transition(Artist, User, string, ?string): Artist`, `markAttendance(Artist, User, string): Artist`, `cancel(Artist, User, string): Artist`. Semua mutasi + history + audit dalam `DB::transaction`; tulis kolom via `unguarded`/`forceFill`.

Aturan (dari spec §1/§4, tanpa konflik — konflik di Task 3):
- `create`: `name` wajib; `scheduled_at` bila diisi wajib dalam rentang event; `duration_minutes` 15–240; status awal `scheduled`, attendance `expected`. 422 pesan Indonesia.
- `update`: field yang sama; `rider_fulfilled`, `status`, `attendance`, `event_id` TIDAK bisa via update (abaikan bila ada — jangan 422, cukup abaikan, agar Form Request tak perlu melarangnya).
- `transition`: maju-satu-langkah per `NEXT`; 422 bila lompat/mundur; tiap sukses → 1 baris history (`from_status`/`to_status`) + audit `artist.transitioned`.
- `markAttendance`: `expected`→`arrived`/`no_show` OK; `arrived`↔`no_show` OK; dari state sama → 422 ('Status kehadiran sudah ...'); history kolom attendance + audit `artist.attendance_marked`. Tak menyentuh status alur.
- `cancel`: dari scheduled/soundcheck/performing → `cancelled`; alasan wajib min 10 karakter (422 bila kosong/pendek — samakan ambang reopen 5B: `mb_strlen(trim($alasan)) >= 10`); dari `done`/`cancelled` → 422; history + audit `artist.cancelled`.

- [ ] **Step 1: Tulis test gagal** (`tests/Feature/ArtistStatusTest.php`, helper prefix `buatArtis()`; setup pola 5B: organization + event + owner — baca `tests/Feature/IncidentStatusTest.php` atau sejenis untuk setup exact, tiru struktur dan nama helper lokalnya):

```php
// contoh isi (sesuaikan setup dengan pola 5B yang dibaca):
it('maju satu langkah alur', function () {
    $artis = buatArtis(['status' => 'scheduled']);
    $hasil = app(ArtistService::class)->transition($artis, $this->owner, 'soundcheck');
    expect($hasil->status)->toBe('soundcheck')
        ->and($artis->histories()->count())->toBe(1);
});
it('lompat langkah ditolak 422', function () {
    $artis = buatArtis(['status' => 'scheduled']);
    expect(fn () => app(ArtistService::class)->transition($artis, $this->owner, 'performing'))
        ->toThrow(HttpException::class, 'Tahap berikutnya');
});
it('cancel butuh alasan min 10 karakter', ...);
it('cancel dari done ditolak', ...);
it('kehadiran arrived lalu no_show bebas bolak-balik', ...);
it('kehadiran tak menyentuh status alur', ...);
```

Minimal 8 test: 3 transisi OK (scheduled→soundcheck→performing→done, boleh 1 test rantai penuh), lompat 422, mundur 422, cancel OK + history, cancel tanpa alasan 422, cancel dari done 422, attendance bolak-balik, attendance independen dari alur.

- [ ] **Step 2: Run test, pastikan gagal**

Run: `docker compose exec app php artisan test --filter=ArtistStatusTest`
Expected: FAIL (kelas `ArtistService` belum ada).

- [ ] **Step 3: Tulis `ArtistService`** (pola `IncidentService::pindah()` — satu method privat `catat()` untuk mutasi+history+audit):

```php
<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistStatusHistory;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArtistService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{name: string, genre?: ?string, stage?: ?string, scheduled_at?: ?string, duration_minutes?: ?int, performance_order?: ?int, contact_name?: ?string, contact_phone?: ?string, rider_text?: ?string} $data */
    public function create(Event $event, User $actor, array $data): Artist
    {
        $this->validasiJadwal($event, $data, null);

        return DB::transaction(function () use ($event, $actor, $data): Artist {
            $artis = Artist::unguarded(fn (): Artist => Artist::create([
                'event_id' => $event->id,
                'name' => $data['name'],
                'genre' => $data['genre'] ?? null,
                'stage' => $data['stage'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'performance_order' => $data['performance_order'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'rider_text' => $data['rider_text'] ?? null,
                'status' => 'scheduled',
                'attendance' => 'expected',
            ]));
            $this->audit->record($actor, 'artist.created', Artist::class, $artis->id, [
                'event_id' => $event->id, 'new' => ['name' => $artis->name],
            ]);

            return $artis;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Artist $artis, User $actor, array $data): Artist
    {
        $this->validasiJadwal($artis->event, $data, $artis);

        return DB::transaction(function () use ($artis, $actor, $data): Artist {
            $artis->forceFill(collect($data)->only([
                'name', 'genre', 'stage', 'scheduled_at', 'duration_minutes',
                'performance_order', 'contact_name', 'contact_phone', 'rider_text',
            ])->all())->save();
            $this->audit->record($actor, 'artist.updated', Artist::class, $artis->id, [
                'event_id' => $artis->event_id,
            ]);

            return $artis->refresh();
        });
    }

    public function transition(Artist $artis, User $actor, string $tujuan, ?string $catatan = null): Artist
    {
        $sah = Artist::NEXT[$artis->status] ?? [];
        abort_unless(in_array($tujuan, $sah, true), 422, 'Tahap berikutnya yang sah: '.($sah === [] ? 'tidak ada.' : implode(', ', $sah).'.'));

        return $this->catat($artis, $actor, ['status' => $tujuan], 'artist.transitioned', $catatan);
    }

    public function markAttendance(Artist $artis, User $actor, string $kehadiran): Artist
    {
        abort_unless(in_array($kehadiran, Artist::ATTENDANCES, true), 422, 'Status kehadiran tidak dikenal.');
        abort_unless($kehadiran !== $artis->attendance, 422, 'Status kehadiran sudah '.$artis->attendance.'.');

        return $this->catat($artis, $actor, ['attendance' => $kehadiran], 'artist.attendance_marked');
    }

    public function cancel(Artist $artis, User $actor, string $alasan): Artist
    {
        abort_unless(in_array($artis->status, ['scheduled', 'soundcheck', 'performing'], true), 422, 'Hanya artis yang belum tampil yang dapat dibatalkan.');
        abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pembatalan minimal 10 karakter.');

        return $this->catat($artis, $actor, ['status' => 'cancelled'], 'artist.cancelled', $alasan);
    }

    /** @param array{status?: string, attendance?: string} $perubahan */
    private function catat(Artist $artis, User $actor, array $perubahan, string $aksi, ?string $catatan = null): Artist
    {
        return DB::transaction(function () use ($artis, $actor, $perubahan, $aksi, $catatan): Artist {
            $artis->forceFill($perubahan)->save();
            ArtistStatusHistory::unguarded(fn (): mixed => $artis->histories()->create([
                'from_status' => array_key_exists('status', $perubahan) ? $artis->getOriginal('status') : null,
                'to_status' => $perubahan['status'] ?? null,
                'from_attendance' => array_key_exists('attendance', $perubahan) ? $artis->getOriginal('attendance') : null,
                'to_attendance' => $perubahan['attendance'] ?? null,
                'actor_id' => $actor->id,
                'note' => $catatan,
            ]));
            $this->audit->record($actor, $aksi, Artist::class, $artis->id, [
                'event_id' => $artis->event_id, 'old' => $artis->getOriginal(), 'new' => $perubahan,
            ]);

            return $artis->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function validasiJadwal(Event $event, array $data, ?Artist $kecuali): void
    {
        // Task 3 mengisi cek konflik di sini. Saat ini: rentang event + durasi saja.
        if (array_key_exists('scheduled_at', $data) && $data['scheduled_at'] !== null) {
            $jadwal = \Carbon\Carbon::parse($data['scheduled_at']);
            abort_unless($jadwal->between($event->start_date, $event->end_date), 422, 'Jadwal harus dalam rentang event.');
        }
        if (array_key_exists('duration_minutes', $data) && $data['duration_minutes'] !== null) {
            abort_unless($data['duration_minutes'] >= 15 && $data['duration_minutes'] <= 240, 422, 'Durasi 15–240 menit.');
        }
    }
}
```

CATATAN: `between()` pada Carbon default inklusif — itu yang diinginkan (jadwal tepat di tanggal mulai/selesai event sah). Nama kolom `start_date`/`end_date` wajib diverifikasi ke migrasi event di Step implementasi; bila berbeda, pakai nama aktual.

- [ ] **Step 4: Run test, pastikan hijau**

Run: `docker compose exec app php artisan test --filter=ArtistStatusTest`
Expected: PASS semua.

- [ ] **Step 5: Pint + Larastan untuk file baru**

Run: `docker compose exec app ./vendor/bin/pint --test app/Services/ArtistService.php app/Models/Artist.php`
Run: `docker compose exec app ./vendor/bin/phpstan analyse --no-progress`
Expected: bersih; bila Pint menandai, jalankan tanpa `--test` lalu commit.

- [ ] **Step 6: Commit**

```bash
git add app/Services/ArtistService.php tests/Feature/ArtistStatusTest.php
git commit -m "feat: artist service status ganda dan cancel

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 3: Validasi konflik (panggung + artis + LO)

**Files:**
- Modify: `app/Services/ArtistService.php` (isi `validasiJadwal()` + method `assignLiaison()` + `releaseLiaison()` + `addNote()` + `toggleRider()`)
- Test: `tests/Feature/ArtistConflictTest.php`

**Interfaces:**
- Consumes: `validasiJadwal()` Task 2; `Registration` accepted (cek cara 5B/volunteer menentukan "event yang ia ikuti" — tiru query-nya; asumsikan `registrations` dengan `status = accepted`, verifikasi nama tabel/kolom di implementasi).
- Produces: `assignLiaison(Artist, User $actor, User $lo): ArtistLiaison`, `releaseLiaison(ArtistLiaison, User $actor): void`, `addNote(Artist, User $penulis, string $isi): ArtistNote`, `toggleRider(Artist, User $actor, bool $terpenuhi): Artist`.

Aturan konflik (spec §3–§4):
- Cek hanya bila `scheduled_at` hasil akhir (data baru atau existing) tidak null. `ends_at` = start + (`duration_minutes` ?? 60).
- (a) Panggung: artis lain event sama, `stage` sama & non-null, rentang overlap (start < ends_lain && ends > start_lain; batas sentuh lolos), kecuali diri sendiri + soft-deleted + `cancelled` → 422 'Bentrok dengan {nama} di panggung yang sama {HH.MM}–{HH.MM}.'
- (b) Artis sama: bila update record yang sama dan rentang barunya overlap dengan... (satu record satu slot — cek artis-sama relevan saat artis punya >1 record? TIDAK: satu row = satu slot, jadi cek (b) hanya bermakna untuk mencegah duplikat via create ganda. Implementasi: pada `create`, bila ada artis lain dengan `name` sama di event sama yang rentangnya overlap → 422 'Jadwal {nama} tumpang tindih ...'. Pada `update`, kecualikan diri sendiri seperti (a).)
- Jadwal null (tanpa `scheduled_at`) → lewati semua cek.
- `assignLiaison`: `$lo` wajib punya registrasi accepted di event artis (422 'Hanya volunteer event ini yang dapat menjadi LO.'); cek overlap dampingan: artis lain event sama yang masih di-LO-kan aktif oleh `$lo` dan rentang keduanya non-null + overlap → 422 'Volunteer sudah mendampingi {nama} pada jam yang sama.'; duplikat aktif (artist,user) → 422 (jadi pengaman selain unique partial).
- `releaseLiaison`: soft-delete liaison + audit `artist.liaison_released`.
- `addNote`: `body` trim min 1, max 2000 (422); audit `artist.note_added`.
- `toggleRider`: set `rider_fulfilled` + audit `artist.rider_toggled` (BUKAN history status).

- [ ] **Step 1: Tulis test gagal** (`tests/Feature/ArtistConflictTest.php`), minimal 9 test:
  1. panggung sama + overlap → 422 menyebut nama pembentrok.
  2. panggung beda + overlap → OK.
  3. panggung sama + batas sentuh (end == start) → OK.
  4. update diri sendiri → OK (tak bentrok dengan dirinya).
  5. artis cancelled/soft-deleted tak dihitung → OK.
  6. jadwal di luar rentang event → 422.
  7. assign LO overlap dampingan → 422.
  8. assign LO non-volunteer event → 422.
  9. jadwal null → OK tanpa cek.

- [ ] **Step 2: Run, pastikan gagal** (cek konflik belum ada → test 1, 7 gagal).

- [ ] **Step 3: Implementasi** — ganti isi `validasiJadwal()` + tambah 4 method publik. Overlap helper privat:

```php
private function rentangOverlap(\Carbon\CarbonInterface $a1, \Carbon\CarbonInterface $a2, \Carbon\CarbonInterface $b1, \Carbon\CarbonInterface $b2): bool
{
    return $a1->lt($b2) && $a2->gt($b1);
}
```

Query kandidat: `Artist::where('event_id', $event->id)->whereNotNull('scheduled_at')->where('status', '!=', 'cancelled')->whereKeyNot($kecuali?->id ?? 0)->get()` lalu filter overlap + stage di PHP (jam event kecil; hindari SQL datetime kompleks — pola pragmatis, catat di laporan bila reviewer tanya).

- [ ] **Step 4: Run** `docker compose exec app php artisan test --filter='ArtistStatusTest|ArtistConflictTest'` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ArtistService.php tests/Feature/ArtistConflictTest.php
git commit -m "feat: validasi konflik jadwal dan liaison

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 4: Policy + Route + Controller organizer & LO + Form Request

**Files:**
- Create: `app/Policies/ArtistPolicy.php`
- Create: 7 Form Request (`StoreArtistRequest`, `UpdateArtistRequest`, `TransitionArtistRequest`, `AssignLiaisonRequest`, `StoreArtistNoteRequest`, `ToggleRiderRequest`, `LiaisonStatusRequest`)
- Create: `app/Http/Controllers/OrganizerArtistController.php`
- Create: `app/Http/Controllers/VolunteerLiaisonController.php`
- Modify: `routes/web.php`
- Modify: `app/Services/MembershipService.php` (sinkronisasi owner — tiru pola incident; bila file ini tak menyebut incident.*, lewati + catat)

**Interfaces:**
- Consumes: semua method `ArtistService` Task 2–3.
- Produces: route names `organizer.events.artists.*` (tiru prefix penamaan 5B: cek `name('incidents.')` → ikuti exactly, mis. `organizer.events.artists.transition`) + `my.liaison.*`.

Policy (pola `IncidentPolicy`):

```php
class ArtistPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && ($user->can('artist.read') || $user->can('artist.liaise') || $user->can('artist.manage'));
    }
    public function view(User $user, Artist $artis): bool { /* sama via $artis->event */ }
    public function manage(User $user, Artist|Event $subject): bool { /* member + artist.manage */ }
    public function liaise(User $user, Artist $artis): bool
    {
        return $artis->liaisons()->where('user_id', $user->id)->exists()
            && $user->can('artist.liaise');
    }
}
```

Route (tiru blok 5B exactly, sisip setelah group `lost-found` ~baris 292):

```php
Route::prefix('artists')->name('artists.')->group(function () {
    Route::get('/', [OrganizerArtistController::class, 'index'])->name('index');
    Route::post('/', [OrganizerArtistController::class, 'store'])
        ->middleware('throttle:30,1')->name('store');
    Route::prefix('{artist}')->group(function () {
        Route::get('/', [OrganizerArtistController::class, 'show'])->name('show');
        Route::put('/', [OrganizerArtistController::class, 'update'])->name('update');
        Route::post('transition', [OrganizerArtistController::class, 'transition'])
            ->middleware('throttle:30,1')->name('transition');
        Route::post('assign', [OrganizerArtistController::class, 'assign'])->name('assign');
        Route::delete('/', [OrganizerArtistController::class, 'destroy'])->name('destroy');
    });
    Route::post('liaisons/{liaison}/release', [OrganizerArtistController::class, 'release'])->name('liaisons.release');
});
```

+ volunteer (dekat `my/lost-found` ~baris 115):

```php
Route::get('my/liaison', [VolunteerLiaisonController::class, 'index'])->name('my.liaison.index');
Route::post('my/liaison/{artist}/status', [VolunteerLiaisonController::class, 'status'])->name('my.liaison.status');
Route::post('my/liaison/{artist}/notes', [VolunteerLiaisonController::class, 'note'])->name('my.liaison.note');
Route::post('my/liaison/{artist}/rider', [VolunteerLiaisonController::class, 'rider'])->name('my.liaison.rider');
```

Scoped binding: `{artist}` di group organizer otomatis scoped org→event→artist bila parent group memakai `scopeBindings` (verifikasi pola 5B di `routes/web.php` — bila 5B memakai `Route::scopedBindings()`, ikuti; test IDOR Task 6 membuktikan). `{artist}` di `my/liaison` di-resolve manual di controller via liaison aktif milik auth (404 bila bukan LO-nya — JANGAN pakai policy `liaise` yang me-return 403; spec menuntut 404 own-scoped).

Form Request rules:
- `StoreArtistRequest::authorize`: member + `artist.manage` (pola `TransitionIncidentRequest::authorize` — cek `$this->route('event')`). rules: `name` required string max 255; `genre`/`stage` nullable string max 100; `scheduled_at` nullable date; `duration_minutes` nullable integer min 15 max 240; `performance_order` nullable integer min 1; `contact_name` nullable string max 255; `contact_phone` nullable string max 50; `rider_text` nullable string max 2000. messages Indonesia.
- `UpdateArtistRequest`: sama dengan `sometimes`.
- `TransitionArtistRequest`: `to` required `Rule::in(Artist::STATUSES)` + `note` nullable max 500 — TAPI service menolak selain NEXT; controller: bila `to === 'cancelled'` panggil `cancel($artis, $user, $note)` (note jadi alasan wajib), selain itu `transition()`. authorize: `manage`.
- `AssignLiaisonRequest`: `user_id` required exists users; authorize `manage`.
- `StoreArtistNoteRequest`: `body` required string max 2000; authorize: `manage` ATAU `liaise` (organizer boleh catat juga).
- `ToggleRiderRequest`: `fulfilled` required boolean; authorize: `manage` ATAU `liaise`.
- `LiaisonStatusRequest`: `to` nullable `Rule::in(STATUSES)` + `attendance` nullable `Rule::in(ATTENDANCES)` (salah satu wajib — `required_without:attendance` / sebaliknya); authorize: liaison aktif milik auth (query di `authorize()`, return bool).

Controller tipis: panggil service, redirect back + flash Indonesia (`'Artis ditambahkan.'`, `'Status diperbarui.'`, dst). `destroy` → service? Spec §1 tidak menuntut method destroy di service — controller panggil `$artis->delete()` + audit langsung? TIDAK: tulis `$this->artis->destroy($artis, $user)` — TAMBAHKAN method `destroy()` ke service di task ini (soft-delete + audit `artist.deleted`). Ini satu-satunya tambahan service di task ini.

- [ ] **Step 1: Tulis controller+request+policy+route** (tanpa test dulu — test HTTP di Task 5-6 memakai jalur ini; bila preferensi TDD ketat, tulis 1 smoke test `GET index → 200` dulu, lihat gagal 404, lalu implementasi).
- [ ] **Step 2: Smoke manual** via test: `docker compose exec app php artisan test --filter=ArtistStatusTest` tetap hijau (tanpa route break).
- [ ] **Step 3: Pint + Larastan.**
- [ ] **Step 4: Commit**

```bash
git add app/Policies/ArtistPolicy.php app/Http/Requests/StoreArtistRequest.php app/Http/Requests/UpdateArtistRequest.php app/Http/Requests/TransitionArtistRequest.php app/Http/Requests/AssignLiaisonRequest.php app/Http/Requests/StoreArtistNoteRequest.php app/Http/Requests/ToggleRiderRequest.php app/Http/Requests/LiaisonStatusRequest.php app/Http/Controllers/OrganizerArtistController.php app/Http/Controllers/VolunteerLiaisonController.php routes/web.php app/Services/MembershipService.php app/Services/ArtistService.php
git commit -m "feat: http organizer artist dan liaison volunteer

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 5: Blade organizer + LO

**Files:**
- Create: `resources/views/organizer/artists/index.blade.php`, `show.blade.php`
- Create: `resources/views/my/liaison/index.blade.php`, `show.blade.php`
- Modify: show event organizer (tambah link/daftar artis — cari `@` view event show; tambah tombol "Kelola Artis" + daftar 5 teratas urut jadwal)

**Interfaces:**
- Consumes: route names + controller data Task 4.
- Produces: halaman hijau tanpa placeholder; form: store, update jadwal, transition (select `to` + note), assign (`user_id` select volunteer — ambil daftar accepted event itu; JANGAN input ID numerik mentah — pelajaran debt 5B "form assign input ID numerik"), release, destroy; LO: status (select to + attendance), note (textarea), rider toggle (checkbox).

Pola Blade: tiru `resources/views/organizer/incidents/*` (cek struktur file-nya di implementasi — layout `x-app-layout` vs extends, komponen form). Filter index: select status + attendance, GET param. Badge status/attendance + flag rider + label LO aktif.

Debt 5B yang JANGAN diulang (dari memori phase5b-debts): form assign harus select (bukan input ID); form transition WAJIB ada input note; form lapor JANGAN tampil untuk user read-only (cek `@can('manage')`).

- [ ] **Step 1–3: Tulis blade per pola incidents, cek render via test HTTP** (`assertSee('...')` — tulis 2 smoke test dalam Task 6, bukan file sendiri).
- [ ] **Step 4: Commit**

```bash
git add resources/views/organizer/artists/ resources/views/my/liaison/ resources/views/organizer/events/
git commit -m "feat: blade artist organizer dan liaison

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 6: Otorisasi + isolasi HTTP

**Files:**
- Test: `tests/Feature/ArtistAuthorizationTest.php` (gaya OperationsAuthorizationTest 5B — baca file itu dulu, tiru peran: staff read-only, lintas org/event, guest, volunteer non-LO)
- Test: `tests/Feature/ArtistIsolationTest.php` (gaya RegistrationIsolationTest — dua org independen simetris)

**Interfaces:** Consumes Task 4–5. Minimal 12 test:
- staff (member tanpa perm) → 403 semua mutasi organizer; index 403.
- volunteer non-LO → 403 index organizer; `POST my/liaison/{id}/status` artis orang lain → 404.
- LO → status/notes/rider artisnya OK; artis lain 404; edit rider-teks (PUT organizer) → 403.
- lintas org/event → 404.
- guest → redirect login.
- LO yang di-release → 404 di scope-nya.
- toggle rider tanpa assignment → 404.
- catatan: edit via PUT artis → tak mengubah notes (append-only; request edit note tak ada route → 404/405 — assert salah satu).
- isolasi: dua org, artis A tak terlihat dari org B (404), LO B tak bisa sentuh artis A.

- [ ] **Step 1: Tulis test gagal** — sebagian gagal (403/404) sebelum policy dipasang? Policy sudah di Task 4 — test ini VERIFIKASI; bila ada yang merah, fix policy/controller di task ini (itu gunanya).
- [ ] **Step 2–4: Run hijau, Pint, Larastan.**
- [ ] **Step 5: Commit**

```bash
git add tests/Feature/ArtistAuthorizationTest.php tests/Feature/ArtistIsolationTest.php
git commit -m "test: otorisasi dan isolasi artist liaison

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 7: Backfill permission + gates akhir

**Files:**
- Create: `database/migrations/2026_09_22_000002_backfill_artist_permissions.php`
- Test: `tests/Feature/BackfillArtistPermissionsTest.php`

**Interfaces:** Tiru `2026_09_20_000006_backfill_incident_permissions.php` exactly (pola `Permission::findOrCreate` + `givePermissionTo` owner aktif; down() cabut selektif). `artist.liaise` untuk user yang punya registrasi accepted AKTIF (belum dicabut?) — definisi: pernah accepted di event mana pun? Pakai: distinct `user_id` dari `registrations` where `status = accepted` (verifikasi nama tabel/kolom di implementasi). down(): cabut `artist.manage` dari bukan-owner-aktif; cabut `artist.liaise` hanya dari yang kini BUKAN volunteer-accepted DAN bukan owner-aktif (lindungi hak sah, pola incident.report).

Test backfill: buat owner lama (tanpa perm) + volunteer accepted + user biasa → jalankan `migrate` → assert owner dapat `artist.manage`, volunteer dapat `artist.liaise`, user biasa tak tersentuh. (Pola `BackfillIncidentPermissionsTest` 5B — baca dulu.)

Gates akhir (wajib sebelum selesai):
- [ ] `php -d memory_limit=1G ./vendor/bin/pest` penuh hijau.
- [ ] Pint bersih, Larastan level 5 bersih, `composer audit` bersih.
- [ ] **Step: Commit**

```bash
git add database/migrations/2026_09_22_000002_backfill_artist_permissions.php tests/Feature/BackfillArtistPermissionsTest.php
git commit -m "feat: backfill permission artist

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- §1 ArtistService sole-writer → Task 2–4 (+`destroy` di Task 4). Status ganda → Task 2. LO assignment → Task 3–4. Rider flag → Task 3–4. Notes append-only → Task 3. Permission manage/liaise/read → Task 1 (seeder) + 4 (policy) + 7 (backfill). Throttle 30/menit → Task 4. ✓
- §2 Empat tabel + constraint + index + unique partial + backfill → Task 1 + 7. `rider_fulfilled` hanya via toggle → Task 3 (tidak fillable di update). ✓
- §3 Route organizer + LO + konflik + LO bentrok + transaksi + IDOR → Task 3–4 + 6. Paginasi 15 + flash Indonesia → Task 4–5 (CATATAN: paginasi `->paginate(15)` di controller index — eksekutor jangan lupa; tidak ada test khusus, verifikasi manual di review). ✓
- §4 Slot default 60 + rentang event + durasi 15–240 + matriks + tiebreak + cancel lepas slot → Task 2–3. `performance_order` tanpa unique → Task 1 (tanpa constraint). ✓
- §5 Seluruh matriks test → Task 2 (status), 3 (konflik), 6 (otorisasi/isolasi/rider), 7 (backfill). Rider: organizer edit OK / LO edit 403 / toggle OK + audit → Task 6 test-nya, implementasi Task 4 (authorize `ToggleRiderRequest` manage|liaise; `UpdateArtistRequest` manage saja → LO otomatis 403). ✓

**2. Placeholder scan:** tak ada TBD/TODO/"handle edge cases" — semua step berisi kode/rules exact. Nilai yang wajib diverifikasi di implementasi ditandai eksplisit (nama kolom event dates, signature AuditLogService, nama tabel registrations, pola scopedBindings, struktur blade incidents). ✓

**3. Type consistency:** `Artist::NEXT/STATUSES/ATTENDANCES/DEFAULT_DURATION` dipakai konsisten Task 1→4. `catat()` menerima `['status'|'attendance']`, history nullable sebelah — konsisten dengan migrasi nullable. `assignLiaison/releaseLiaison/addNote/toggleRider/destroy` signature didefinisikan di Task 3–4 sebelum dipakai. Route names mengikuti pola 5B. ✓
