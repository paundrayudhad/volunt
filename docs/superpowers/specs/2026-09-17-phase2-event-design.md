# Phase 2 (Event) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–4) pada 2026-09-17.
**Scope:** event, division, role, shift, public page (katalog + search/filter). (PRD §11)
**Non-scope:** custom registration form (Phase 3), `QuotaService` atomik + concurrency test (Phase 3), upload file/banner (ditunda hingga ada consumer), API/Sanctum (tetap ditunda per ARCHITECTURE.md), otomatisasi status berbasis tanggal, modul event musik (Phase 5).

Keputusan kunci (hasil klarifikasi 2026-09-17): public page = katalog + search/filter (nama, kategori/kota, pagination, tanpa auth); state machine penuh 8 state + Cancelled; quota = kolom `accepted_count` + check constraint saja; shift penuh kecuali upload; tanpa cache publik dulu (YAGNI).

## 1. Arsitektur & Komponen

Modular monolith (ARCHITECTURE.md §1): satu modul domain baru — `Events` (event, division, role, shift, katalog publik) — menempel pada rantai tenancy Phase 1: `User → Organization → Event → Division → Role → Shift`.

Alur lapisan (sama seperti Phase 1): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service; tidak ada logic di Blade/Livewire view.

| Service | Tanggung jawab |
|---|---|
| `EventService` | CRUD event; `transitionTo()` tervalidasi; CRUD division/role/shift ter-scope event; satu-satunya penulis `status` event |
| `AuditLogService` / `SecurityService` | Dipakai ulang dari Phase 1 (kolom `event_id` di `audit_logs` akhirnya terisi) |

Tanpa service terpisah per sub-resource (division/role/shift tidak punya state machine sendiri, selalu diakses dalam konteks event) — YAGNI. `QuotaService` atomik eksplisit non-scope Phase 2.

Model otorisasi (ARCHITECTURE.md §4, SECURITY.md §3): permission Spatie = **kemampuan**; `organization_members.role` + membership = **cakupan**. Setiap cek = `user->can('...')` **dan** `$user->belongsToOrganization($event->organization_id)`. Owner mendapat seluruh permission event via sinkronisasi di `MembershipService` (tambah ke `GRANULAR`/`OWNER_PERMS`); `STAFF_BASE` tetap view-only.

Permission granular baru: `event.create`, `event.view`, `event.update`, `event.delete`, `event.publish` (transisi state), `division.manage`, `role.manage`, `shift.manage`. Konsisten dengan SECURITY.md §5 (`event.create`/`event.update` untuk staff; delete/cancel owner-only).

Kebijakan auth di atas Phase 1: password min 12, throttle login, `verified` middleware tetap berlaku pada group organizer; katalog publik tanpa auth + throttle ringan anti-scrape (60/menit per IP).

## 2. Data & Migrasi

Tabel baru (selain bawaan Phase 0–1):

- **`events`** — `organization_id` (FK cascade), `name`, `slug` (unique per org: `UNIQUE(organization_id, slug)`), `description` (nullable), `category` (nullable), `venue` (nullable), `address` (nullable), `latitude`/`longitude` (nullable decimal 10,7), `timezone` (default `Asia/Jakarta`), `start_at`, `end_at`, `registration_start_at`/`registration_end_at` (nullable), `status` (check: `draft`, `published`, `registration_open`, `registration_closed`, `ongoing`, `completed`, `archived`, `cancelled`), `capacity` (nullable int), `contact` (jsonb nullable), `branding` (jsonb nullable), `banner_path`/`thumbnail_path` (nullable — kolom disiapkan, endpoint upload ditunda), `terms`/`privacy_notice` (nullable text), `published_at` (nullable), soft deletes. Check: `end_at > start_at`, `registration_start_at < registration_end_at`. Index: `(organization_id, status)`, `(status, start_at)`. Kolom `organization_id`, `status` **tidak fillable** — di-set server-side (`status` hanya via `transitionTo()`).
- **`event_divisions`** — `event_id` (FK cascade), `name`, `description` (nullable), `supervisor_id → users` (nullable), `status`. Unique `(event_id, name)`.
- **`event_roles`** — `event_id`, `division_id` (FK cascade), `name`, `description` (nullable), `quota` (default 0), **`accepted_count` default 0**, `requirements` (jsonb nullable), `location` (nullable), `status`. Unique `(event_id, division_id, name)`. Check: `quota >= 0`, `accepted_count >= 0`, `accepted_count <= quota`.
- **`event_shifts`** — `event_id`, `division_id`, `role_id` (FK cascade, nullable — shift boleh menempel division langsung), `start_at`, `end_at`, `location` (nullable), `capacity` (nullable), `supervisor_id → users` (nullable), `status`. Check: `end_at > start_at`, `capacity >= 0`. Index: `(event_id, start_at)`, `(role_id, start_at)`.

Relasi Eloquent: `Organization hasMany Event`; `Event belongsTo Organization + hasMany Division/Role/Shift`; `Division belongsTo Event + hasMany Role/Shift`; `Role belongsTo Event+Division + hasMany Shift`; `Shift belongsTo Event+Division (+Role nullable)`. Scope: `forOrganization()`, `forEvent()`, `active()`, `published()` mengikuti pola `scopeActive()` Phase 1.

## 3. Alur Request & Otorisasi

Route groups (`routes/web.php`, menempel pola Phase 1):

- **Publik** (tanpa auth) — `GET /events` (katalog: event berstatus `published`/`registration_open`/`registration_closed`/`ongoing` + search nama + filter kategori/kota + pagination 12) dan `GET /events/{event}` (detail publik: info event + division/role dengan sisa kuota + jadwal shift; tanpa data member/internal). Binding slug global dengan scope status publik — event non-publik → **404**. Throttle 60/menit per IP. Tanpa cache dulu (YAGNI; invalidasi prematur menambah bug).
- **`/organizer/{organization}/events`** — CRUD event + `POST .../transition` (ganti state via `transitionTo()`; cancel wajib `reason` + `password.confirm`) + CRUD division/role/shift nested di bawah `{event}`. Binding scoped ganda: `{organization}` milik user (pola Phase 1), lalu `{event}` di-resolve dalam org tersebut (dan `{division}`/`{role}`/`{shift}` dalam event tersebut); di luar scope → **404**. Policy: `EventPolicy`, `DivisionPolicy`, `RolePolicy`, `ShiftPolicy` (cek `can()` **dan** `belongsToOrganization($event->organization_id)`).
- **`/admin`** — daftar semua event (paginasi) + suspend/cancel paksa (re-auth + alasan teraudit). Log tetap read-only.

State machine (penuh): `draft → published → registration_open → registration_closed → ongoing → completed → archived`, plus `cancelled` dari `published`/`registration_open`/`registration_closed`/`ongoing`. `transitionTo()` menolak transisi invalid dengan 422; `cancelled`/`archived` menolak perubahan turunan (edit event/division/role/shift). `Cancelled` wajib alasan (`reason` required, teraudit). Registration window tidak mengubah status otomatis — status murni dikendalikan organizer via transition.

IDOR (SECURITY.md §4): resource tenant di luar scope → 404; aksi yang resource-nya terlihat namun tidak diizinkan → 403. Konsisten per resource.

Transaksi wajib (`DB::transaction`): create event + audit; transition + audit; create/update/delete division/role/shift + audit; cancel paksa admin + audit.

Semua input via Form Request; `$fillable` allowlist eksplisit; `organization_id`, `event_id`, status sensitif tidak pernah fillable.

## 4. Testing & Gates

Mengikuti TESTING.md, scope Phase 2:

- **Unit** — `EventService`: transition valid lolos (draft→published→…→archived), invalid ditolak (draft→completed, archived→apapun), cancel tanpa alasan ditolak, `cancelled`/`archived` menolak edit; quota check di level constraint; policy unit per resource (pemilik scope lolos, luar scope gagal).
- **Feature** — CRUD event end-to-end (buat→publish→buka registrasi→tutup→mulai→selesai→arsip); CRUD division/role/shift dalam event; katalog publik (search/filter/pagination, event non-publik → 404); cancel dengan alasan + re-auth.
- **Authorization** — matriks per endpoint: guest → redirect login (organizer) / 200 (publik); staff tanpa `event.create` → 403; staff beda org → 404; volunteer → 403 organizer, 200 publik; non-admin di `/admin/events` → 403.
- **Isolation** — Org A vs Org B simetris atas events/divisions/roles/shifts (404/403 tanpa bocor); katalog publik tidak membocorkan event non-publik org manapun.
- **Negatif** — mass-assignment (`organization_id`, `status` via request ditolak); slug duplikat dalam org ditolak; `end_at <= start_at` ditolak; transisi dari terminal state ditolak; tanpa concurrency test (menyusul Phase 3).
- **Gates** — Pest hijau; Pint PASS; PHPStan/Larastan level 5 No errors; `composer audit` bersih critical/high.

## 5. Self-Review

1. **Placeholder scan:** seluruh tabel/kolom/status/permission bernama eksplisit; batas numerik konkret (pagination 12, throttle publik 60/menit, quota default 0, timezone default `Asia/Jakarta`). Tanpa TBD/TODO.
2. **Konsistensi internal:** "kolom upload disiapkan, endpoint ditunda" (§2) konsisten dengan non-scope §4; "tanpa cache" (§3) konsisten dengan ARCHITECTURE.md §6 (cache hanya bila traffic menuntut); `event.publish` terpisah dari `event.update` memungkinkan staff edit tanpa hak publish; registration window pasif konsisten dengan "tanpa otomatisasi tanggal".
3. **Scope:** murni struktur event + katalog publik; custom fields, quota atomik, upload, API eksplisit non-scope. Satu-satunya service baru adalah `EventService` — division/role/shift ikut di dalamnya (YAGNI service terpisah).
4. **Ambiguitas:** `role_id` nullable di shift adalah keputusan eksplisit (shift boleh menempel division langsung); `supervisor_id` nullable di division/shift (diisi Phase 4 saat assignment ada); `capacity` event vs `quota` role vs `capacity` shift adalah tiga angka independen (agregasi/validasi lintas-level menyusul Phase 3–4).
