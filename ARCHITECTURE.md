# ARCHITECTURE

## 1. Gaya Arsitektur: Modular Monolith Ringan

Satu aplikasi Laravel, tanpa microservices. Modul dipisahkan per domain
(`Identity`, `Tenancy`, `Events`, `Recruitment`, `Operations`, `Comms`,
`Credentials`, `Reporting`, `Platform`) lewat namespace service + policy,
bukan lewat network boundary.

```text
Browser
  ↓ HTTPS
Nginx → PHP-FPM → Laravel 13
  ├── Routes (web.php / api.php)
  ├── Controllers (tipis: auth, validasi, panggil service)
  ├── Livewire Components + Blade + Alpine.js
  ├── Form Requests (validasi + authorize)
  ├── Policies (otorisasi per resource)
  ├── Services (seluruh business logic)
  ├── Jobs + Notifications (async)
  └── Models (Eloquent, relasi + scope)
        ↓
  PostgreSQL 16+ (sumber kebenaran)
```

Redis opsional: ditambahkan hanya untuk queue/cache/session saat traffic
menuntut. Abstraksi Laravel (`Queue`, `Cache`, `Session`) membuat
perpindahan storage tanpa mengubah business logic.

## 2. Keputusan Stack (dengan alasan)

| Pilihan | Alasan |
|---|---|
| Laravel 13 + PHP 8.4 | Versi LTS aktif; typed code, atribut validasi, performa |
| Blade + Livewire 4.4+ + Alpine.js | Server-rendered, hemat VPS; reaktivitas tanpa SPA |
| Tailwind CSS 4 | Utility-first, build ringan |
| PostgreSQL (primary) | FK, unique parsial, check constraint, transaksi, `FOR UPDATE` |
| Spatie Permission | RBAC matang; dilengkapi membership + scope (bukan global role saja) |
| Sanctum **ditunda** | YAGNI — dibangun saat API eksternal dibutuhkan; kontrak di [API.md](API.md) |
| Pest + Pint + PHPStan | Test ekspresif, format PSR-12, static analysis |

Versi terverifikasi saat penulisan: Laravel 13 (rilis 17 Mar 2026, butuh
PHP 8.3+), Livewire v4.4.4 stabil. Verifikasi ulang saat scaffold Phase 0.

## 3. Model Tenancy: Shared Schema + Ownership Chain

Satu database, satu schema. Setiap resource penting membawa rantai
kepemilikan hingga tenant:

```text
User → Organization → Event → Division → Role → Shift
User → Registration → Assignment → Attendance
```

Penegakan tiga lapis (semuanya backend):

1. **Scoped query** — setiap query resource difilter `organization_id` /
   `event_id` dari server context, bukan dari parameter client.
2. **Policy** — `EventPolicy`, `RegistrationPolicy`, dst. memeriksa
   membership + permission + ownership chain.
3. **Route model binding scoped** — `events/{event}` di-resolve dalam
   lingkup organisasi user; di luar scope → 404 (tidak membocorkan
   keberadaan resource) atau 403 sesuai kebijakan (lihat [SECURITY.md](SECURITY.md)).

## 4. RBAC + Membership + Scope

- Role global Spatie hanya untuk `super_admin`.
- Akses tenant berasal dari `organization_members`:
  `Owner` (penuh 1 org) dan `Staff` (permission configurable:
  `event.update`, `registration.review`, `volunteer.assign`,
  `attendance.read`, `announcement.create`, `report.export`, ...).
- Permission Spatie memberi *kemampuan*; membership memberi *cakupan*.
  Keduanya harus lolos: `can('registration.review')` **dan**
  `belongsToOrganization($event->organization_id)`.

## 5. Service Layer (controller tetap tipis)

Seluruh aturan bisnis di service; controller hanya: auth → Form Request →
panggil service → response. Tidak ada business logic di Blade/Livewire view.

| Service | Tanggung jawab |
|---|---|
| `EventService` | CRUD event, state transition tervalidasi |
| `RegistrationService` | Submit, review, accept/reject/waitlist, withdraw |
| `QuotaService` | Counter atomik accept/release (satu-satunya penulis `accepted_count`) |
| `AssignmentService` | Assign/reassign + history |
| `AttendanceService` | Check-in/out + validasi QR/time window |
| `CertificateService` | Eligibility + generate + verifikasi |
| `NotificationService` | Dispatch async multi-channel |
| `ExportService` | Export scoped + rate limit + queue |
| `AuditLogService` / `SecurityService` | Pencatatan audit & security event |

Livewire component memanggil service yang sama — tidak ada duplikasi logic
antara web, Livewire, dan (nanti) API.

## 6. Abstraksi Infrastruktur

- **Queue**: `QUEUE_CONNECTION=database` (MVP) → `redis` tanpa ubah job.
  Untuk: email, notifikasi, sertifikat, export besar, broadcast, image processing.
- **Storage**: disk `local` → `s3`/R2 via `Storage` facade. Nama file acak,
  direktori non-executable, tanpa path dari client.
- **Cache**: hanya data publik (daftar event, detail event, profil organizer)
  dengan key ber-scope (`events:list:{filter-hash}`, `event:{id}` + invalidasi
  saat update). Data privat tidak di-cache lintas user.
- **Session**: cookie `Secure + HttpOnly + SameSite=Lax`, rotasi saat login,
  invalidasi saat logout. Tanpa token auth di `localStorage`.

## 7. Struktur Direktori (rencana scaffold)

```text
app/
├── Http/Controllers/      # tipis: Auth, Public, Organizer/*, Admin/*, Api/*
├── Livewire/              # Public/, Volunteer/, Organizer/, Admin/
├── Http/Requests/         # Form Request per aksi (authorize + rules)
├── Policies/              # satu policy per resource tenant-scoped
├── Services/              # domain services (lihat §5)
├── Jobs/                  # queued jobs
├── Notifications/         # database/mail (+channel lain menyusul)
├── Models/                # dengan scope: forOrganization(), forEvent(), active()
└── Support/               # Idempotency, QR token, sanitizer, export writer
routes/
├── web.php                # public, volunteer, organizer, admin
└── api.php                # dibangun setelah fondasi web (lihat API.md)
```

## 8. Alur Request Kritis (contoh: accept registration)

```text
POST organizer/events/{event}/registrations/{reg}/accept
 → auth → throttle → Form Request (authorize: policy + scope)
 → RegistrationService::accept() dalam DB::transaction():
     1. lock event_roles row (FOR UPDATE)
     2. cek status valid (Under Review/Waitlisted) + quota tersedia
     3. update registration → Accepted; accepted_count++
     4. tulis registration_status_histories + audit log
     5. dispatch notifikasi (queue)
 → response redirect + flash
```

Kegagalan di langkah mana pun → rollback penuh.

## 9. Prinsip Kualitas Kode

PSR-12 (Pint), strict typing bila sesuai, Form Request untuk semua input,
Policy untuk semua resource, penamaan jelas, tanpa god class/controller,
tanpa permission/tenant/event ID hardcoded, tanpa logic bisnis di Blade.
