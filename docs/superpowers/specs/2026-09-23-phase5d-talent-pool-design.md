# Phase 5D (Talent Pool & Volunteer Invitation) — Design Spec

**Goal:** Membangun modul **Talent Pool** terisolasi per organisasi untuk mencari relawan berpengalaman dari riwayat event sebelumnya (berdasarkan skill, lokasi, riwayat role, persentase kehadiran, dan sertifikat) serta mengirimkan **Undangan Event** langsung ke relawan.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, Spatie Permission, Pest, Blade.

---

## 1. Core Architecture & Privacy Rules

1. **Tenant Isolation:**
   - Pencarian Talent Pool terisolasi secara independen pada level `Organization`.
   - Staf `Organization A` **hanya** dapat melihat relawan (`User`) yang memiliki minimal 1 registrasi dengan status `accepted` (atau riwayat partisipasi) pada event-event milik `Organization A`.
   - Tidak ada pencarian global lintas tenant demi menjaga privasi data dan kedaulatan data tenant.

2. **Privacy & Data Minimization:**
   - Pilihan `visibility` dari profil relawan (`volunteer_profiles.visibility`) dihormati secara penuh:
     - `private`: Relawan disembunyikan total dari pencarian Talent Pool.
     - `organizers` & `public`: Relawan dapat ditemukan oleh organisasi tempat ia pernah berpartisipasi.
   - Minimasi data sensitif: Nomor telepon, alamat jalan lengkap, dan kontak darurat disembunyikan pada hasil pencarian dan portofolio Talent Pool. Kontak hanya dibuka saat relawan menerima undangan atau berada dalam event aktif yang sama.

3. **Multi-Criteria Search & Filtering:**
   - Pencarian kata kunci (nama, email/username, bio).
   - Filter *Skills & Minat* (`volunteer_profiles.skills`).
   - Filter *Riwayat Event & Role* di organisasi bersangkutan.
   - Filter *Domisili / Kota* (`volunteer_profiles.city`).
   - Filter *Tingkat Kehadiran Minimal* (persentase rasion kehadiran pada shift ter-assign).

---

## 2. Database Schema Changes

### Migrasi: `database/migrations/2026_09_23_000001_create_event_invitations_table.php`

```php
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

        DB::statement("ALTER TABLE event_invitations ADD CONSTRAINT event_invitations_status_check CHECK (status IN ('pending', 'accepted', 'declined', 'expired', 'cancelled'))");
        DB::statement("CREATE UNIQUE INDEX event_invitations_pending_unique ON event_invitations (event_id, user_id) WHERE status = 'pending' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
    }
};
```

---

## 3. Permissions & Backfill

- Permission baru:
  - `talent.search`: Mengakses halaman Talent Pool dan melakukan pencarian/filter.
  - `talent.invite`: Mengirim undangan event ke relawan dari Talent Pool.
- Inisialisasi Seeder: Tambahkan permission ke `database/seeders/PermissionSeeder.php`.
- Migrasi Backfill: `database/migrations/2026_09_23_000002_backfill_talent_permissions.php` untuk memberikan `talent.search` dan `talent.invite` secara otomatis kepada semua pemilik organisasi (`Organization Owner`) aktif.

---

## 4. Business Logic & Services

### `TalentPoolService`
- `search(Organization $organization, User $actor, array $filters): LengthAwarePaginator`
  - Memastikan `$actor` memiliki hak akses `talent.search` di organisasi.
  - Melakukan query `User::query()` yang terhubung ke `registrations` milik `$organization->id` (status `accepted`).
  - Menerapkan filter: `q`, `skills`, `event_ids`, `role_ids`, `city`, `min_attendance_pct`.
  - Mengabaikan relawan dengan `visibility = 'private'`.
  - Menghitung agregat per relawan: `total_events`, `total_certificates`, `attendance_rate`.
  - Mencatat log audit: `talent.searched`.

### `InvitationService`
- `invite(Event $event, User $actor, User $volunteer, ?int $roleId, ?string $message): EventInvitation`
  - Memvalidasi otorisasi `$actor` (`talent.invite`).
  - Memvalidasi status event (bukan `archived` / `cancelled`).
  - Mencegah undangan ganda jika ada undangan `pending` yang sama.
  - Mencegah undangan jika relawan sudah mendaftar/diterima pada event target (422: *"Relawan sudah terdaftar pada event ini."*).
  - Menyetel masa berlaku default 7 hari (`expires_at = now()->addDays(7)`).
  - Mencatat log audit: `talent.invited`.

- `respond(EventInvitation $invitation, User $volunteer, string $action): EventInvitation`
  - Memvalidasi `$invitation->user_id === $volunteer->id` dan status `pending` serta belum `expired`.
  - Jika `$action === 'accepted'`:
    - Membuat record `Registration` baru untuk `$volunteer` di `$invitation->event_id` dengan status `accepted` (atau `pending` jika ada field kustom).
    - Memotong kuota role via `QuotaService` secara atomik (`SELECT ... FOR UPDATE`). Jika kuota penuh, melempar exception 422.
    - Mengubah status undangan menjadi `accepted` dan mengisi `responded_at = now()`.
    - Mencatat log audit: `talent_invitation.accepted`.
  - Jika `$action === 'declined'`:
    - Mengubah status undangan menjadi `declined` dan mengisi `responded_at = now()`.
    - Mencatat log audit: `talent_invitation.declined`.

- `cancel(EventInvitation $invitation, User $actor): EventInvitation`
  - Membatalkan undangan `pending` oleh organizer.

---

## 5. HTTP Routes & Policies

### Routes (`routes/web.php`)

```php
// Area Organizer
Route::prefix('organizer/{organization:slug}')->name('organizer.')->group(function () {
    Route::get('talent', [OrganizerTalentController::class, 'index'])->name('talent.index');
    Route::get('talent/{user}', [OrganizerTalentController::class, 'show'])->name('talent.show');
    Route::post('events/{event}/invitations', [OrganizerTalentController::class, 'invite'])
        ->middleware('throttle:30,1')->name('talent.invite');
    Route::delete('events/{event}/invitations/{invitation}', [OrganizerTalentController::class, 'cancel'])
        ->name('talent.invitation.cancel');
});

// Area Relawan
Route::middleware(['auth'])->group(function () {
    Route::get('my/invitations', [VolunteerInvitationController::class, 'index'])->name('my.invitations.index');
    Route::post('my/invitations/{invitation}/respond', [VolunteerInvitationController::class, 'respond'])
        ->middleware('throttle:30,1')->name('my.invitations.respond');
});
```

### Form Requests & Policies
- `SearchTalentRequest`: Validasi parameter query pencarian.
- `StoreInvitationRequest`: Validasi `user_id` (required), `role_id` (nullable, exists di `event_roles`), `message` (nullable, max 1000).
- `RespondInvitationRequest`: Validasi `action` (`accepted`, `declined`).
- `TalentPolicy`: Guard otorisasi untuk `search`, `view`, `invite`.

---

## 6. User Interface (Blade Views)

1. `resources/views/organizer/talent/index.blade.php`:
   - Panel pencarian dan filter samping (skills, kota, riwayat role, min attendance).
   - Kartu relawan dengan badge skill, rating/kehadiran %, dan tombol modal *"Undang ke Event"*.
2. `resources/views/organizer/talent/show.blade.php`:
   - Portofolio internal relawan pada organisasi tersebut (daftar event yang diikuti, performa kehadiran, sertifikat).
3. `resources/views/my/invitations/index.blade.php`:
   - Daftar undangan event masuk untuk relawan beserta detail pesan organizer dan tombol respon *Terima* / *Tolak*.

---

## 7. Testing Strategy

1. **`TalentPoolSearchTest.php`**
   - Menguji pencarian berdasarkan skill, kota, dan riwayat role.
   - Menguji penghormatan terhadap pengaturan privasi profil (`visibility = 'private'`).
   - Menguji isolasi tenant mutlak (Organizer A tidak dapat melihat relawan milik Organizer B).

2. **`EventInvitationTest.php`**
   - Menguji pengiriman undangan sukses & pencegahan undangan ganda (`pending`).
   - Menguji kadaluarsa undangan setelah 7 hari.
   - Menguji alur penerimaan undangan yang otomatis membuat registrasi dan memotong kuota role secara atomik.
   - Menguji otorisasi staf organizer tanpa permission (`403`).
