# Phase 5B (Incident + Lost & Found) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pelaporan & penanganan insiden event dan barang hilang-temu dengan rantai status beraudit, alur klaim ringan, dan satu foto per item yang diserve aman.

**Architecture:** Dua service sole-writer (`IncidentService`, `LostFoundService`) di atas tiga tabel baru + satu tabel history; controller tipis → Form Request → Service → Model; foto di disk `local` non-publik, diserve via route signed 30 menit.

**Tech Stack:** Laravel 13 / PHP 8.4 (via `docker compose exec app`), PostgreSQL 16, Blade, Spatie permission, Pest, Pint, Larastan level 5.

**Spec:** `docs/superpowers/specs/2026-09-20-phase5b-incident-lostfound-design.md`

## Global Constraints

- Semua perintah PHP/composer/artisan via `docker compose exec app ...` (host PHP 8.2, container 8.4).
- Alur lapisan: controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model; business logic hanya di service.
- Otorisasi: permission Spatie = kemampuan AND membership = cakupan (`can()` + `belongsToOrganization()`); owner dapat semua perm via sync; `STAFF_BASE` tetap `['organization.view', 'member.view', 'event.view']` view-only.
- Audit log append-only via `AuditLogService::record(User $actor, string $action, string $resourceType, int|string $resourceId, array $context = [])`.
- Check constraints + partial unique index via `DB::statement` di migrasi (pola `2026_09_16_000001_create_organizations_table.php`).
- Prose Indonesia (UI, pesan error, flash), identifier/kode/branch/commit English.
- Setiap step konten nyata tanpa TBD/TODO/placeholder; `git add` file eksplisit setelah `git status --porcelain`, NEVER `git add -A` (also NEVER `-f`).
- Commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`; kerja in place (tanpa worktree).
- Test helper prefix per file (contoh `buatInsiden*`, `laporBarang*`); Pest hijau penuh + Pint + Larastan level 5 + `composer audit` bersih per task.

---

### Task 1: Data & Model (migrasi, relasi, permission, factory)

**Files:**
- Create: `database/migrations/2026_09_20_000004_create_incidents_tables.php`
- Create: `database/migrations/2026_09_20_000005_create_lost_found_items_table.php`
- Create: `database/migrations/2026_09_20_000006_backfill_incident_permissions.php`
- Create: `app/Models/Incident.php`
- Create: `app/Models/IncidentStatusHistory.php`
- Create: `app/Models/LostFoundItem.php`
- Create: `database/factories/IncidentFactory.php`
- Create: `database/factories/LostFoundItemFactory.php`
- Modify: `app/Models/Event.php` (tambah `incidents()`, `lostFoundItems()`)
- Modify: `app/Models/User.php` (tambah `reportedIncidents()`, `reportedLostFoundItems()`)
- Modify: `app/Services/MembershipService.php` (GRANULAR +3)
- Modify: `database/seeders/PermissionSeeder.php` (PERMISSIONS +3)
- Test: `tests/Feature/IncidentModelRelationTest.php`

**Interfaces:**
- Consumes: `AuditLogService::record()` (untuk Task 2); pola check-constraint 5A.
- Produces: `Incident::STATUSES`, `Incident::NEXT` (map state → daftar state berikut yang sah), `Incident::CATEGORIES`, `Incident::PRIORITIES`, `LostFoundItem::KINDS`, `LostFoundItem::STATUSES`; relasi `Event::incidents()/lostFoundItems()`; permission `incident.manage`, `incident.report`, `lostfound.manage`.

- [ ] **Step 1: Write the failing test (relasi + permission terdaftar)**

```php
// tests/Feature/IncidentModelRelationTest.php
use App\Models\Event;
use App\Models\Incident;
use App\Models\LostFoundItem;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function buatEventInsiden(): Event
{
    return Event::factory()->create();
}

it('insiden terhubung event dan reporter', function () {
    $event = buatEventInsiden();
    $pelapor = User::factory()->create();
    $insiden = Incident::factory()->create(['event_id' => $event->id, 'reporter_id' => $pelapor->id]);

    expect($insiden->event->id)->toBe($event->id)
        ->and($insiden->reporter->id)->toBe($pelapor->id)
        ->and($event->incidents()->count())->toBe(1);
});

it('permission granular 5B terdaftar', function () {
    foreach (['incident.manage', 'incident.report', 'lostfound.manage'] as $nama) {
        expect(Permission::where('name', $nama)->exists())->toBeTrue();
    }
});

it('item lost&found terhubung event', function () {
    $event = buatEventInsiden();
    $item = LostFoundItem::factory()->create(['event_id' => $event->id, 'kind' => 'found']);

    expect($item->event->id)->toBe($event->id)
        ->and($event->lostFoundItems()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/IncidentModelRelationTest.php`
Expected: FAIL with "Class App\Models\Incident not found".

- [ ] **Step 3: Write migration incidents + histories**

```php
// database/migrations/2026_09_20_000004_create_incidents_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('priority', 10)->default('medium');
            $table->string('location', 255);
            $table->text('description');
            $table->string('attachment_path', 255)->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->foreignId('lost_found_item_id')->nullable()->constrained('lost_found_items')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'priority']);
        });
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_category_check CHECK (category IN ('medical','security','crowd','technical','lost_found','other'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_priority_check CHECK (priority IN ('low','medium','high','critical'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_check CHECK (status IN ('open','assigned','in_progress','resolved','closed'))");

        Schema::create('incident_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_histories');
        Schema::dropIfExists('incidents');
    }
};
```

CATATAN: `lost_found_items` harus dibuat dulu (FK incidents → lost_found_items). Beri nomor migrasi lost_found `2026_09_20_000004`, incidents `2026_09_20_000005`, backfill `2026_09_20_000006`. Timestamp prefix menentukan urutan jalan — file 000004 = lost_found, 000005 = incidents+histories.

- [ ] **Step 4: Write migration lost_found_items**

```php
// database/migrations/2026_09_20_000004_create_lost_found_items_table.php
Schema::create('lost_found_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('kind', 10);
    $table->string('item_name', 255);
    $table->text('description')->nullable();
    $table->string('photo_path', 255)->nullable();
    $table->string('location', 255)->nullable();
    $table->timestamp('occurred_at')->nullable();
    $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('claimant_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('claimed_at')->nullable();
    $table->string('status', 20)->default('open');
    $table->timestamps();
    $table->softDeletes();
    $table->index(['event_id', 'status']);
    $table->index(['event_id', 'kind']);
});
DB::statement("ALTER TABLE lost_found_items ADD CONSTRAINT lost_found_items_kind_check CHECK (kind IN ('lost','found'))");
DB::statement("ALTER TABLE lost_found_items ADD CONSTRAINT lost_found_items_status_check CHECK (status IN ('open','found','claimed','returned','closed'))");
```

- [ ] **Step 5: Write models (fillable kosong, konstanta, relasi)**

```php
// app/Models/Incident.php
class Incident extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = ['medical', 'security', 'crowd', 'technical', 'lost_found', 'other'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    public const STATUSES = ['open', 'assigned', 'in_progress', 'resolved', 'closed'];
    public const NEXT = [
        'open' => ['assigned'],
        'assigned' => ['in_progress'],
        'in_progress' => ['resolved'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = ['deleted_at' => 'datetime'];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function lostFoundItem(): BelongsTo { return $this->belongsTo(LostFoundItem::class); }
    public function histories(): HasMany { return $this->hasMany(IncidentStatusHistory::class); }
    public function isCritical(): bool { return $this->priority === 'critical'; }
}
```

`IncidentStatusHistory`: `$timestamps = false; protected $fillable = [];`, relasi `incident()`, `actor()`. `LostFoundItem`: `KINDS = ['lost','found']`, `STATUSES = ['open','found','claimed','returned','closed']`, casts `occurred_at`/`claimed_at` datetime, relasi `event/reporter/handler/claimant/incidents`.

Event: tambah `incidents(): HasMany`, `lostFoundItems(): HasMany`. User: tambah `reportedIncidents()`, `reportedLostFoundItems()` (hasMany via reporter_id).

- [ ] **Step 6: Permission (GRANULAR + Seeder + backfill)**

Tambah ke `MembershipService::GRANULAR` dan `PermissionSeeder::PERMISSIONS`: `'incident.manage'`, `'incident.report'`, `'lostfound.manage'` (setelah `'certificate.read'`, urutan sama di kedua file). `OWNER_PERMS = GRANULAR` otomatis ikut.

Backfill `2026_09_20_000006_backfill_incident_permissions.php` — salin pola `2026_09_20_000003_backfill_certificate_permissions.php` dengan `$perms = ['incident.manage', 'incident.report', 'lostfound.manage']` (guard Permission-exists + down() hanya cabut dari bukan-owner-aktif).

- [ ] **Step 7: Factories**

`IncidentFactory`: event via `Event::factory()`, reporter via `User::factory()`, category `security`, priority `medium`, location `'Pintu masuk utama'`, description `'Kabel sound terkelupas.'`, status `open`. `LostFoundItemFactory`: kind `found`, item_name `'Dompet kulit cokelat'`, status `found`, occurred_at now.

- [ ] **Step 8: Run test + migrate:fresh --seed, Pint, PHPStan**

Run: `docker compose exec app php artisan test tests/Feature/IncidentModelRelationTest.php`
Expected: PASS (3 tests).
Run: `docker compose exec app php artisan migrate:fresh --seed` lalu full `docker compose exec app php artisan test`, `docker compose exec app ./vendor/bin/pint --test`, `docker compose exec app ./vendor/bin/phpstan analyse --level=5 --no-progress`.

- [ ] **Step 9: Commit**

```bash
git status --porcelain
git add database/migrations/2026_09_20_000004_create_lost_found_items_table.php database/migrations/2026_09_20_000005_create_incidents_tables.php database/migrations/2026_09_20_000006_backfill_incident_permissions.php app/Models/Incident.php app/Models/IncidentStatusHistory.php app/Models/LostFoundItem.php database/factories/IncidentFactory.php database/factories/LostFoundItemFactory.php app/Models/Event.php app/Models/User.php app/Services/MembershipService.php database/seeders/PermissionSeeder.php tests/Feature/IncidentModelRelationTest.php
git commit -m @'
feat: model dan migrasi insiden + lost & found

Co-Authored-By: Claude Code <noreply@anthropic.com>
'@
```

### Task 2: Service (state machine, klaim, foto, penghubung)

**Files:**
- Create: `app/Services/IncidentService.php`
- Create: `app/Services/LostFoundService.php`
- Create: `app/Services/StoredPhoto.php`
- Test: `tests/Feature/IncidentServiceTest.php`
- Test: `tests/Feature/LostFoundServiceTest.php`

**Interfaces:**
- Consumes: model + konstanta Task 1; `AuditLogService::record()`; `Registration` accepted untuk validasi volunteer.
- Produces: `IncidentService::report/assign/transition/reopen`, `LostFoundService::report/claim/resolveClaim/close`, `StoredPhoto::fromUpload(UploadedFile $file, string $dir): string` (return path relatif storage). Task 3 memanggil semuanya dari controller.

- [ ] **Step 1: Write failing tests (rantai + klaim + backfill)**

```php
// tests/Feature/IncidentServiceTest.php
use App\Models\Event;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\IncidentService;

function buatInsidenOrg(): array {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->givePermissionTo(['incident.manage', 'incident.report']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    return [$org, $owner, $event];
}

it('rantai maju selangkah dan mencatat history', function () {
    [$org, $owner, $event] = buatInsidenOrg();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'medical', 'priority' => 'high', 'location' => 'Panggung kiri', 'description' => 'Penonton pingsan di depan panggung.']);

    expect($insiden->status)->toBe('open');
    $tugas = User::factory()->create();
    OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $tugas->id, 'role' => 'staff', 'status' => 'active']);
    $svc->assign($insiden, $owner, $tugas);
    $svc->transition($insiden->refresh(), $owner, 'in_progress');

    expect($insiden->refresh()->status)->toBe('in_progress')
        ->and($insiden->histories()->count())->toBe(2);
});

it('lompat langkah ditolak 422', function () {
    [$org, $owner, $event] = buatInsidenOrg();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'crowd', 'priority' => 'medium', 'location' => 'Pintu B', 'description' => 'Antrean menumpuk di pintu masuk B.']);

    $svc->transition($insiden, $owner, 'resolved');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class, 'Tahap berikutnya yang sah: assigned.');

it('reopen dari resolved kembali open', function () {
    [$org, $owner, $event] = buatInsidenOrg();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'technical', 'priority' => 'low', 'location' => 'FOH', 'description' => 'Mic cadangan mati saat cek suara.']);
    foreach (['assigned', 'in_progress', 'resolved'] as $tahap) {
        if ($tahap === 'assigned') { $svc->assign($insiden->refresh(), $owner, $owner); }
        else { $svc->transition($insiden->refresh(), $owner, $tahap); }
    }
    $svc->reopen($insiden->refresh(), $owner, 'Kerusakan muncul lagi saat gladi.');

    expect($insiden->refresh()->status)->toBe('open');
});
```

```php
// tests/Feature/LostFoundServiceTest.php (cuplikan, helper laporBarang*)
it('klaim lalu setuju menjadi returned', function () {
    // ... setup org/event seperti di atas via helper sendiri
    $item = $svc->report($event, $pelapor, ['kind' => 'found', 'item_name' => 'Kunci motor', 'location' => 'Posko informasi']);
    $svc->claim($item, $pengklaim);
    expect($item->refresh()->status)->toBe('claimed');
    $svc->resolveClaim($item->refresh(), $handler, 'returned', 'Cocok dengan ciri.');
    expect($item->refresh()->status)->toBe('returned');
});

it('tolak klaim kembali found dan claimant dibersihkan', function () { /* ... expect status found + claimant_id null */ });
it('klaim ganda ditolak 422', function () { /* ... */ });
it('klaim milik sendiri ditolak', function () { /* ... abort 422 'Tidak dapat mengklaim laporan sendiri.' */ });
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/IncidentServiceTest.php tests/Feature/LostFoundServiceTest.php`
Expected: FAIL with "Class App\Services\IncidentService not found".

- [ ] **Step 3: Implement StoredPhoto**

```php
// app/Services/StoredPhoto.php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoredPhoto
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 4096;
    public const ALLOWED = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public static function fromUpload(UploadedFile $file, string $dir): string
    {
        abort_unless($file->isValid(), 422, 'Berkas foto tidak valid.');
        abort_unless($file->getSize() !== false && $file->getSize() <= self::MAX_BYTES, 422, 'Ukuran foto maksimal 5MB.');
        $info = @getimagesize($file->getRealPath());
        abort_unless($info !== false, 422, 'Berkas bukan gambar yang valid.');
        abort_unless($info[0] <= self::MAX_SIDE && $info[1] <= self::MAX_SIDE, 422, 'Dimensi foto maksimal 4096px.');
        $ekstensi = strtolower($file->getClientOriginalExtension());
        abort_unless(array_key_exists($ekstensi, self::ALLOWED), 422, 'Format foto: jpg, png, atau webp.');
        abort_unless(in_array(mime_content_type($file->getRealPath()), self::ALLOWED, true), 422, 'Isi berkas bukan gambar yang didukung.');
        $nama = Str::uuid()->toString().'.'.$ekstensi;
        $path = trim($dir, '/').'/'.$nama;
        if (extension_loaded('gd')) {
            $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            abort_unless($img !== false, 422, 'Berkas bukan gambar yang valid.');
            $menulis = match ($ekstensi) {
                'png' => imagepng($img, Storage::disk('local')->path($path)),
                'webp' => imagewebp($img, Storage::disk('local')->path($path)),
                default => imagejpeg($img, Storage::disk('local')->path($path), 90),
            };
            imagedestroy($img);
            abort_unless($menulis, 422, 'Foto gagal disimpan.');
        } else {
            Storage::disk('local')->putFileAs(trim($dir, '/'), $file, $nama);
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function mimeFor(string $path): string
    {
        return self::ALLOWED[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
```

- [ ] **Step 4: Implement IncidentService**

```php
// app/Services/IncidentService.php
namespace App\Services;

use App\Models\Event;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{category: string, priority?: string, location: string, description: string, lost_found_item_id?: int} $data */
    public function report(Event $event, User $pelapor, array $data): Incident
    {
        abort_unless(in_array($data['category'], Incident::CATEGORIES, true), 422, 'Kategori insiden tidak dikenal.');
        $prioritas = $data['priority'] ?? 'medium';
        abort_unless(in_array($prioritas, Incident::PRIORITIES, true), 422, 'Prioritas insiden tidak dikenal.');

        return DB::transaction(function () use ($event, $pelapor, $data, $prioritas): Incident {
            $insiden = Incident::unguarded(fn (): Incident => Incident::create([
                'event_id' => $event->id,
                'category' => $data['category'],
                'priority' => $prioritas,
                'location' => $data['location'],
                'description' => $data['description'],
                'reporter_id' => $pelapor->id,
                'status' => 'open',
                'lost_found_item_id' => $data['lost_found_item_id'] ?? null,
            ]));
            $this->audit->record($pelapor, 'incident.reported', Incident::class, $insiden->id, [
                'event_id' => $event->id, 'new' => ['category' => $insiden->category, 'priority' => $insiden->priority],
            ]);

            return $insiden;
        });
    }

    public function assign(Incident $insiden, User $actor, User $tugas): Incident
    {
        abort_unless($tugas->belongsToOrganization($insiden->event->organization_id), 422, 'Petugas harus member organisasi yang sama.');
        abort_unless($insiden->status === 'open', 422, 'Hanya insiden open yang dapat ditugaskan.');

        return $this->pindah($insiden, $actor, 'assigned', ['assignee_id' => $tugas->id], 'incident.assigned');
    }

    public function transition(Incident $insiden, User $actor, string $tujuan, ?string $catatan = null): Incident
    {
        $sah = Incident::NEXT[$insiden->status] ?? [];
        abort_unless(in_array($tujuan, $sah, true), 422, 'Tahap berikutnya yang sah: '.($sah === [] ? 'tidak ada.' : implode(', ', $sah).'.'));

        return $this->pindah($insiden, $actor, $tujuan, [], 'incident.transitioned', $catatan);
    }

    public function reopen(Incident $insiden, User $actor, string $alasan): Incident
    {
        abort_unless(in_array($insiden->status, ['resolved', 'closed'], true), 422, 'Hanya insiden resolved/closed yang dapat dibuka ulang.');
        abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pembukaan ulang minimal 10 karakter.');

        return $this->pindah($insiden, $actor, 'open', ['assignee_id' => null], 'incident.reopened', $alasan);
    }

    /** @param array<string, mixed> $tambahan */
    private function pindah(Incident $insiden, User $actor, string $tujuan, array $tambahan, string $aksi, ?string $catatan = null): Incident
    {
        return DB::transaction(function () use ($insiden, $actor, $tujuan, $tambahan, $aksi, $catatan): Incident {
            $dari = $insiden->status;
            $insiden->forceFill(array_merge(['status' => $tujuan], $tambahan))->save();
            $insiden->histories()->create(['from_status' => $dari, 'to_status' => $tujuan, 'actor_id' => $actor->id, 'note' => $catatan]);
            $this->audit->record($actor, $aksi, Incident::class, $insiden->id, [
                'event_id' => $insiden->event_id, 'old' => ['status' => $dari], 'new' => ['status' => $tujuan],
            ]);

            return $insiden->refresh();
        });
    }
}
```

- [ ] **Step 5: Implement LostFoundService**

```php
// app/Services/LostFoundService.php
public function report(Event $event, User $pelapor, array $data): LostFoundItem  // kind lost→status open, found→status found; foto via StoredPhoto::fromUpload($data['photo'], 'lost-found') bila ada; audit lostfound.reported
public function claim(LostFoundItem $item, User $pengklaim): LostFoundItem
// abort 404 bila status !== 'found' ('Barang tidak tersedia untuk diklaim.');
// abort 422 bila claimant sudah ada ('Barang sudah diklaim.');
// abort 422 bila reporter sendiri ('Tidak dapat mengklaim laporan sendiri.');
// forceFill claimed + claimant_id + claimed_at now(); audit lostfound.claimed
public function resolveClaim(LostFoundItem $item, User $handler, string $keputusan, ?string $catatan = null): LostFoundItem
// abort 422 bila status !== 'claimed'; abort 422 bila keputusan ∉ returned|rejected
// returned → status returned + handler_id; rejected → status found + claimant dibersihkan (claimant_id null, claimed_at null) + handler_id; audit lostfound.claim_resolved
public function close(LostFoundItem $item, User $actor): LostFoundItem
// abort 422 kecuali status found|returned; → closed; audit lostfound.closed
```

Semua metode membungkus tulis + audit dalam `DB::transaction`.

- [ ] **Step 6: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/IncidentServiceTest.php tests/Feature/LostFoundServiceTest.php`
Expected: PASS. Lalu full suite + Pint + PHPStan seperti Task 1 Step 8.

- [ ] **Step 7: Commit**

```bash
git status --porcelain
git add app/Services/IncidentService.php app/Services/LostFoundService.php app/Services/StoredPhoto.php tests/Feature/IncidentServiceTest.php tests/Feature/LostFoundServiceTest.php
git commit -m @'
feat: service insiden rantai status + lost & found klaim

Co-Authored-By: Claude Code <noreply@anthropic.com>
'@
```

### Task 3: HTTP Organizer (request, policy, controller, route, blade)

**Files:**
- Create: `app/Http/Requests/StoreIncidentRequest.php`
- Create: `app/Http/Requests/TransitionIncidentRequest.php`
- Create: `app/Http/Requests/ReopenIncidentRequest.php`
- Create: `app/Http/Requests/AssignIncidentRequest.php`
- Create: `app/Http/Requests/StoreLostFoundRequest.php`
- Create: `app/Http/Requests/ResolveClaimRequest.php`
- Create: `app/Policies/IncidentPolicy.php`
- Create: `app/Policies/LostFoundPolicy.php`
- Create: `app/Http/Controllers/Organizer/IncidentController.php`
- Create: `app/Http/Controllers/Organizer/LostFoundController.php`
- Create: `resources/views/organizer/events/incidents/index.blade.php`
- Create: `resources/views/organizer/events/incidents/show.blade.php`
- Create: `resources/views/organizer/events/lost-found/index.blade.php`
- Create: `resources/views/organizer/events/lost-found/show.blade.php`
- Modify: `app/Providers/AppServiceProvider.php` (policy + binding `incident`, `lostFoundItem`)
- Modify: `routes/web.php` (group incidents + lost-found + foto signed)
- Test: `tests/Feature/OrganizerIncidentTest.php`

**Interfaces:**
- Consumes: service Task 2; pola `IssueCertificatesRequest::authorize()` (union Event|Model) + pola binding `certificate` scoped event.
- Produces: route names `organizer.events.incidents.*`, `organizer.events.lost_found.*`, `lostfound.photo`; binding `incident`/`lostFoundItem` scoped event (luar scope → 404).

- [ ] **Step 1: Write failing tests (CRUD HTTP + filter + link + 404 lintas event)**

```php
// tests/Feature/OrganizerIncidentTest.php
it('organizer lapor insiden lalu transisi via http', function () {
    // setup org+owner+event (helper kelolaInsiden*), perm incident.manage+report
    $res = $this->actingAs($owner)->post(route('organizer.events.incidents.store', [$org->slug, $event->slug]), [
        'category' => 'security', 'priority' => 'critical', 'location' => 'Pintu A', 'description' => 'Keributan di antrean pintu masuk A.',
    ]);
    $res->assertRedirect();
    $insiden = Incident::where('event_id', $event->id)->firstOrFail();
    $this->actingAs($owner)->post(route('organizer.events.incidents.assign', [$org->slug, $event->slug, $insiden->id]), ['assignee_id' => $staff->id])->assertRedirect();
    $this->actingAs($owner)->post(route('organizer.events.incidents.transition', [$org->slug, $event->slug, $insiden->id]), ['to' => 'in_progress'])->assertRedirect();
    expect($insiden->refresh()->status)->toBe('in_progress');
});

it('lompat tahap via http 422 dengan pesan', function () { /* post transition to=resolved dari open → assert session error 'Tahap berikutnya' */ });
it('index filter status dan badge critical tampil', function () { /* buat 2 insiden, get index?status=open, assertSee teks + badge KRITIS */ });
it('insiden lintas event 404', function () { /* GET show dengan slug event lain → 404 */ });
it('staff read-only 403 saat assign', function () { /* actingAs staff (STAFF_BASE) post assign → 403 */ });
it('lost&found lapor + resolve klaim via http', function () { /* store lost_found, claim sebagai volunteer lain, resolve returned */ });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/OrganizerIncidentTest.php`
Expected: FAIL (route `organizer.events.incidents.store` not defined).

- [ ] **Step 3: Form Requests + Policies**

`StoreIncidentRequest::authorize()`: event dari route instanceof Event → member + `can('incident.report')`. rules: category in CATEGORIES, priority nullable in PRIORITIES, location required max 255, description required min 10, lost_found_item_id nullable exists lost_found_items ( + validasi event sama di controller/service — service `report()` cek item milik event yang sama else 422 'Item tertaut bukan milik event ini.'). messages Indonesia.

`AssignIncidentRequest`: rules assignee_id required exists users. `TransitionIncidentRequest`: to required in STATUSES. `ReopenIncidentRequest`: reason required min 10. `StoreLostFoundRequest`: kind in lost|found, item_name required max 255, description nullable, photo nullable image mimes jpg,jpeg,png,webp max 5120, location nullable, occurred_at nullable date. `ResolveClaimRequest`: decision in returned|rejected, note nullable max 500. `authorize()` masing-masing: member + perm sesuai (store → incident.report; assign/transition/reopen → incident.manage; resolve → lostfound.manage).

`IncidentPolicy`: `viewAny(User, Event)` = member + (can incident.report || can incident.manage); `view(User, Incident)` sama via `$incident->event`; `manage(User, Incident|Event)` = member + can incident.manage. `LostFoundPolicy`: `viewAny/view` = member + (can lostfound.manage || can incident.report); `manage` = member + can lostfound.manage. Daftarkan di AppServiceProvider + binding:

```php
Route::bind('incident', function (string $value): Incident {
    $event = request()->route()?->parameter('event');
    $eventId = $event instanceof EventModel ? $event->getKey() : null;
    return Incident::whereKey($value)->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))->firstOrFail();
});
// pola sama untuk lostFoundItem (parameter route {lostFoundItem})
```

- [ ] **Step 4: Controllers (tipis, try HttpException → back withErrors)**

`Organizer\IncidentController`: `__construct(IncidentService, LostFoundService?)` — hanya IncidentService. index (filter category/status/priority via query, paginate 15, with reporter/assignee), show (load histories.actor + lostFoundItem), store (FormRequest validated → service report; tangkap HttpException non-404 → back withErrors), assign, transition, reopen, destroy (soft-delete + audit incident.deleted, redirect index + flash 'Insiden diarsipkan.').

`Organizer\LostFoundController`: index (filter kind/status, paginate 15), show (+ incidents balik via `$item->incidents`), store (validated + photo file → service), resolveClaim, close, destroy. Semua redirect + flash Indonesia. Gate::authorize policy per aksi.

- [ ] **Step 5: Routes web.php (di dalam group event, setelah certificates)**

```php
Route::prefix('incidents')->name('incidents.')->group(function () {
    Route::get('/', [OrganizerIncidentController::class, 'index'])->name('index');
    Route::post('/', [OrganizerIncidentController::class, 'store'])
        ->middleware('throttle:30,1')->name('store');
    Route::prefix('{incident}')->group(function () {
        Route::get('/', [OrganizerIncidentController::class, 'show'])->name('show');
        Route::post('assign', [OrganizerIncidentController::class, 'assign'])->name('assign');
        Route::post('transition', [OrganizerIncidentController::class, 'transition'])->name('transition');
        Route::post('reopen', [OrganizerIncidentController::class, 'reopen'])->name('reopen');
        Route::delete('/', [OrganizerIncidentController::class, 'destroy'])->name('destroy');
    });
});
Route::prefix('lost-found')->name('lost_found.')->group(function () {
    Route::get('/', [OrganizerLostFoundController::class, 'index'])->name('index');
    Route::post('/', [OrganizerLostFoundController::class, 'store'])
        ->middleware('throttle:30,1')->name('store');
    Route::prefix('{lostFoundItem}')->group(function () {
        Route::get('/', [OrganizerLostFoundController::class, 'show'])->name('show');
        Route::post('resolve-claim', [OrganizerLostFoundController::class, 'resolveClaim'])->name('resolve');
        Route::post('close', [OrganizerLostFoundController::class, 'close'])->name('close');
        Route::delete('/', [OrganizerLostFoundController::class, 'destroy'])->name('destroy');
    });
});
```

Alias controller: `use App\Http\Controllers\Organizer\LostFoundController as OrganizerLostFoundController;`. Foto signed (di luar group org, area auth umum):

```php
Route::get('lost-found-photos/{lostFoundItem}', [LostFoundPhotoController::class, 'show'])
    ->middleware(['auth', 'signed'])->name('lostfound.photo');
```

(binding `{lostFoundItem}` untuk foto = by-id tanpa scope event; scope dicek di controller via membership → 404.)

- [ ] **Step 6: Blade (4 file, prose Indonesia, badge KRITIS)**

index incidents: tabel (kategori, prioritas + badge `KRITIS` bila critical, status, lokasi, pelapor, waktu) + form filter (select category/status/priority, GET) + link Lapor. show: detail + badge + history rantai (tabel from→to, actor, waktu, note) + item tertaut (bila ada) + form assign/transition/reopen sesuai status. index/show lost-found: tabel (barang, kind HILANG/TEMU, status, lokasi, foto thumbnail via signed URL 30 mnt, claimant bila claimed) + form resolve (returned/rejected + note) bila claimed + tombol Tutup bila found/returned. Semua halaman `@extends` layout proyek yang sama dengan halaman certificates (cek `resources/views/organizer/events/certificates/index.blade.php` untuk nama layout & section).

- [ ] **Step 7: Run tests + gates**

Run: `docker compose exec app php artisan test tests/Feature/OrganizerIncidentTest.php` → PASS; full suite + Pint + PHPStan.

- [ ] **Step 8: Commit**

```bash
git status --porcelain
git add app/Http/Requests/StoreIncidentRequest.php app/Http/Requests/TransitionIncidentRequest.php app/Http/Requests/ReopenIncidentRequest.php app/Http/Requests/AssignIncidentRequest.php app/Http/Requests/StoreLostFoundRequest.php app/Http/Requests/ResolveClaimRequest.php app/Policies/IncidentPolicy.php app/Policies/LostFoundPolicy.php app/Http/Controllers/Organizer/IncidentController.php app/Http/Controllers/Organizer/LostFoundController.php "resources/views/organizer/events/incidents/index.blade.php" "resources/views/organizer/events/incidents/show.blade.php" "resources/views/organizer/events/lost-found/index.blade.php" "resources/views/organizer/events/lost-found/show.blade.php" app/Providers/AppServiceProvider.php routes/web.php tests/Feature/OrganizerIncidentTest.php
git commit -m @'
feat: http organizer insiden + lost & found

Co-Authored-By: Claude Code <noreply@anthropic.com>
'@
```

### Task 4: HTTP Volunteer + foto signed

**Files:**
- Create: `app/Http/Controllers/Volunteer/IncidentController.php` (alias `VolunteerIncidentController` di route)
- Create: `app/Http/Controllers/Volunteer/LostFoundController.php` (alias `VolunteerLostFoundController`)
- Create: `app/Http/Controllers/LostFoundPhotoController.php`
- Create: `resources/views/volunteer/incidents/index.blade.php`
- Create: `resources/views/volunteer/lost-found/index.blade.php`
- Modify: `routes/web.php` (group `my/` + foto signed)
- Test: `tests/Feature/VolunteerIncidentTest.php`

**Interfaces:**
- Consumes: `IncidentService::report`, `LostFoundService::report/claim`, `StoredPhoto::mimeFor`, binding Task 3, route names Task 3.
- Produces: route `my.incidents.*`, `my.lost_found.*`, `lostfound.photo`; halaman volunteer selesai. Task 5 menguji otorisasi/isolasi di atasnya.

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/VolunteerIncidentTest.php
it('volunteer lapor insiden di event yang ia ikuti', function () {
    // volunteer dengan registration accepted di event
    $this->actingAs($vol)->post(route('my.incidents.store'), ['event_id' => $event->id, 'category' => 'medical', 'priority' => 'high', 'location' => 'Depan panggung', 'description' => 'Penonton jatuh pingsan di depan panggung.'])
        ->assertRedirect();
    expect(Incident::where('reporter_id', $vol->id)->count())->toBe(1);
});

it('volunteer tak bisa lapor di event yang tak ia ikuti → 404', function () { /* post event lain → 404 */ });
it('volunteer klaim barang found', function () { /* post claim → claimed + claimant diri */ });
it('klaim milik sendiri 422', function () { /* ... */ });
it('foto tanpa signature 403, dengan signature 200', function () {
    $item = /* found + photo_path terisi (upload fake) */;
    $this->actingAs($vol)->get("/lost-found-photos/{$item->id}")->assertForbidden();
    $url = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30));
    $this->actingAs($vol)->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg');
});
it('foto lintas org 404', function () { /* signed URL valid tapi org lain → 404 */ });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/VolunteerIncidentTest.php`
Expected: FAIL (route `my.incidents.store` not defined).

- [ ] **Step 3: Controllers volunteer + foto**

`Volunteer\IncidentController@index`: insiden yang ia laporkan (`reporter_id` = auth) + paginate 15. `store`: validasi (event_id exists events; category/priority/location/description sama; tanpa lampiran — volunteer teks saja, YAGNI) → cek registration accepted di event itu (bila tidak → `abort(404)`) → service report → redirect + flash 'Laporan insiden terkirim.'.

`Volunteer\LostFoundController@index`: dua kelompok — laporan miliknya + barang `found` dari event yang ia ikuti (untuk diklaim). `store`: validasi + cek registration accepted → service report (dengan foto opsional) → flash. `claim`: load item by-id → cek item milik event yang ia ikuti (registration accepted, else 404) → service claim → flash 'Klaim tercatat, hubungi posko untuk verifikasi.'.

`LostFoundPhotoController@show(LostFoundItem $item)`: `abort_unless($item->photo_path !== null, 404)`; `abort_unless(auth()->user()->belongsToOrganization($item->event->organization_id), 404)`; `return Storage::disk('local')->response($item->photo_path, null, ['Content-Type' => StoredPhoto::mimeFor($item->photo_path), 'Content-Disposition' => 'inline'])`.

- [ ] **Step 4: Routes (dekat `my/certificates`, pola sama)**

```php
Route::get('my/incidents', [VolunteerIncidentController::class, 'index'])->name('my.incidents.index');
Route::post('my/incidents', [VolunteerIncidentController::class, 'store'])
    ->middleware('throttle:30,1')->name('my.incidents.store');
Route::get('my/lost-found', [VolunteerLostFoundController::class, 'index'])->name('my.lost_found.index');
Route::post('my/lost-found', [VolunteerLostFoundController::class, 'store'])
    ->middleware('throttle:30,1')->name('my.lost_found.store');
Route::post('my/lost-found/{lostFoundItem}/claim', [VolunteerLostFoundController::class, 'claim'])
    ->middleware('throttle:30,1')->name('my.lost_found.claim');
```

(binding `{lostFoundItem}` di rute volunteer = by-id; scope dicek controller → 404.)

- [ ] **Step 5: Blade volunteer (2 file)**

`volunteer/incidents/index`: daftar laporan miliknya (status badge, kategori, event, waktu) + form lapor (select event yang ia ikuti + fields). `volunteer/lost-found/index`: laporanku + barang temu (tabel + tombol Klaim bila found & bukan miliknya) + form lapor (kind, nama, lokasi, foto). Layout sama dengan `volunteer` views existing (cek `resources/views/volunteer/*` / halaman certificates volunteer).

- [ ] **Step 6: Run tests + gates**

`docker compose exec app php artisan test tests/Feature/VolunteerIncidentTest.php` → PASS; full + Pint + PHPStan.

- [ ] **Step 7: Commit**

```bash
git status --porcelain
git add app/Http/Controllers/Volunteer/IncidentController.php app/Http/Controllers/Volunteer/LostFoundController.php app/Http/Controllers/LostFoundPhotoController.php resources/views/volunteer/incidents/index.blade.php resources/views/volunteer/lost-found/index.blade.php routes/web.php tests/Feature/VolunteerIncidentTest.php
git commit -m @'
feat: http volunteer insiden + lost & found + foto signed

Co-Authored-By: Claude Code <noreply@anthropic.com>
'@
```

### Task 5: Otorisasi, isolasi, backfill, ketahanan batch

**Files:**
- Test: `tests/Feature/IncidentAuthorizationTest.php`
- Test: `tests/Feature/IncidentIsolationTest.php`
- Test: `tests/Feature/IncidentBackfillTest.php`
- Modify: file sumber HANYA bila temuan (fix round)

**Interfaces:**
- Consumes: seluruh Task 1–4. Produces: gate akhir Phase 5B (gaya `OperationsAuthorizationTest` + `RegistrationIsolationTest` + `sertTesBackfillMemberiPermOwnerLama`).

- [ ] **Step 1: Write authorization test (gaya OperationsAuthorizationTest)**

```php
// tests/Feature/IncidentAuthorizationTest.php
- guest akses index organizer → redirect login; guest foto → redirect login.
- staff (STAFF_BASE) GET index insiden → 403; POST store insiden → 403; POST transition → 403; POST resolve-claim → 403; DELETE insiden → 403.
- staff dengan incident.report (diberi manual): store OK 302, tapi assign/transition/reopen/destroy → 403.
- lintas org: owner org A GET show insiden org B → 404; POST transition → 404.
- lintas event (satu org): show insiden event lain via slug event → 404.
- volunteer: assign/transition/close/resolve (rute organizer) → 403 atau 404 (pilih sesuai perilaku: bukan member dengan perm → 403 dari policy; bila binding org menolak → 404; tulis ekspektasi sesuai implementasi Task 3 — JANGAN menebak, jalankan dan sesuaikan ekspektasi dengan kontrak spec IDOR: luar scope → 404, terlihat tapi tak diizinkan → 403).
```

- [ ] **Step 2: Write isolation test (gaya RegistrationIsolationTest, dua org simetris)**

```php
// tests/Feature/IncidentIsolationTest.php
- Org A dan B masing-masing: event + insiden + item + transisi + klaim.
- Index A hanya menampilkan milik A (count + tidak mengandung nama barang B).
- History insiden A tak tercampur B; claim B tak memengaruhi A.
- Foto A via signed URL tak bisa diakses member B (404).
```

- [ ] **Step 3: Write backfill + ketahanan tests (pelajaran Critical 5A)**

```php
// tests/Feature/IncidentBackfillTest.php
it('backfill memberi perm 5B ke owner lama', function () {
    // buat owner + member TANPA perm 5B (revoke manual), jalankan migrasi up via (new Migrasi)->up() atau Artisan::call('migrate'),
    // expect owner can incident.manage/report + lostfound.manage; non-owner tetap tidak.
});
it('report dengan item event lain ditolak', function () {
    // service report dengan lost_found_item_id milik event lain → HttpException 422 'Item tertaut bukan milik event ini.'
});
it('assign ke non-member ditolak', function () {
    // service assign dengan user luar org → 422 'Petugas harus member organisasi yang sama.'
});
it('hapus item menullkan link insiden, insiden tetap ada', function () {
    // insiden + item tertaut; $item->delete(); expect insiden refresh tetap ada + lost_found_item_id null.
});
it('hapus insiden, item tetap ada', function () {
    // insiden + item tertaut; $insiden->delete(); expect item refresh tetap ada.
});
```

CATATAN: bila `report()` Task 2 belum memvalidasi event item tertaut (spec §3 menuntutnya tapi Task 2 tak menyebut), tambahkan di Task 5 sebagai fix round eksplisit — bukan diam-diam: tulis test dulu (Step 3 di atas), lalu edit `IncidentService::report()`:

```php
if (! empty($data['lost_found_item_id'])) {
    $taut = LostFoundItem::whereKey($data['lost_found_item_id'])->first();
    abort_unless($taut !== null && $taut->event_id === $event->id, 422, 'Item tertaut bukan milik event ini.');
}
```

- [ ] **Step 4: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/IncidentAuthorizationTest.php tests/Feature/IncidentIsolationTest.php tests/Feature/IncidentBackfillTest.php`
Expected: PASS (bila FAIL → fix round: edit sumber minimal, re-run, maksimal sesuai SDD).

- [ ] **Step 5: Full gates + commit**

Run full: `docker compose exec app php artisan test`, Pint, PHPStan level 5, `docker compose exec app composer audit`, `docker compose exec app php artisan migrate:fresh --seed`.

```bash
git status --porcelain
git add tests/Feature/IncidentAuthorizationTest.php tests/Feature/IncidentIsolationTest.php tests/Feature/IncidentBackfillTest.php <file-fix-bila-ada>
git commit -m @'
test: otorisasi isolasi backfill insiden + lost & found

Co-Authored-By: Claude Code <noreply@anthropic.com>
'@
```

## Self-Review

**1. Spec coverage:**
- §1 service sole-writer + NEXT map + critical flag visual (badge di blade Task 3) → Task 2, 3. `assign` wajib member org sama → Task 2 Step 4 + test Task 5 Step 3. Throttle store 30/mnt → Task 3 Step 5, Task 4 Step 4. Tanpa password.confirm → tidak ada di route. ✅
- §2 tiga tabel + check via DB::statement + index + relasi + fillable kosong → Task 1. `lost_found_item_id` nullOnDelete → Task 1 Step 3. History append-only (tanpa update/delete path, `$timestamps=false`) → Task 1 Step 5. Default status by kind (lost→open, found→found) → Task 2 Step 5. ✅
- §3 route groups + binding scoped + filter + volunteer registration-accepted check + signed foto 30 mnt + transaksi + IDOR → Task 3, 4. `close` dari found/returned → Task 2 Step 5 `close()` (abort kecuali found|returned). `assignee` member org sama → Task 2. ✅
- §4 StoredPhoto (5MB, allowlist, UUID, traversal, re-encode GD bila ada + getimagesize, 4096px, replace hapus lama, serve signed) → Task 2 Step 3 + Task 4 Step 3. Replace-hapus-lama: implementasi di `LostFoundService` saat report/update foto — CATATAN: plan belum menulis update-foto; store hanya create sekali. Karena tidak ada endpoint update foto, aturan "upload baru hapus lama" tak terpicu — DEFERRED wajar (YAGNI, tanpa endpoint edit). Soft-delete pertahankan file, force-delete tak ada → konsisten. ✅
- §5 seluruh daftar uji → Task 2 (rantai, klaim), Task 3 (penghubung show dua arah? — show blade menampilkan kedua arah: show insiden tampilkan item, show item tampilkan `$item->incidents` ✅; hapus sisi-satu-sisi-lain-tetap → FK nullOnDelete, test ditambah di Task 5 Step 3? Belum ada — TAMBAHKAN ke Task 5 Step 3: `it('hapus item menullkan link insiden')` + `it('hapus insiden item tetap ada')`). Foto matrix → Task 4. Otorisasi/isolasi/backfill → Task 5. ✅

**2. Placeholder scan:** tidak ada TBD/TODO/"menyesuaikan"/"dsb". Semua angka konkret (15, 30, 10, 5MB, 4096, 500, 255). ✅ (kecuali catatan fix-conditional Task 5 Step 3 yang eksplisit, bukan placeholder).

**3. Type consistency:** `report(Event, User, array): Incident/LostFoundItem`; `assign/transition/reopen/claim/resolveClaim/close` return model refresh; `StoredPhoto::fromUpload(UploadedFile, string): string`; route names konsisten `organizer.events.incidents.*` / `*.lost_found.*` / `my.incidents.*` / `my.lost_found.*` / `lostfound.photo`; binding params `{incident}`, `{lostFoundItem}`; policy methods `viewAny/view/manage`. ✅

**Perbaikan dari self-review (sudah inline di atas):** Task 5 Step 3 perlu dua test penghubung hapus (spec §5 "hapus item → insiden tetap ada (link null); hapus insiden → item tetap ada") — tambahkan saat eksekusi.
