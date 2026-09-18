# Phase 3 (Volunteer) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–4) pada 2026-09-18.
**Scope:** volunteer profile, registration, custom fields (13 tipe penuh), seleksi + bulk, waitlist manual, quota atomik + concurrency test. (PRD §7–9)
**Non-scope:** assignment/schedule/attendance (Phase 4), upload banner (tetap ditunda), API/Sanctum (tetap ditunda), auto-promosi waitlist, export/report (Phase 6).

Keputusan kunci (hasil klarifikasi 2026-09-18): pendekatan A (`RegistrationService` + `QuotaService` terpisah); custom fields penuh 13 tipe termasuk file (SECURITY.md §9); waitlist manual saja; bulk actions ya (maks 50 ID, satu transaction); profil lengkap + visibility.

## 1. Arsitektur & Komponen

Modular monolith (ARCHITECTURE.md §1): satu modul domain baru — `Volunteers` (profile, registration, custom fields, seleksi) — menempel pada rantai tenancy Phase 2: `User → Organization → Event → Role → Registration` (volunteer selalu masuk lewat role yang dipilih; registrations menempel `event_id` + `role_id`).

Alur lapisan (sama seperti Phase 1–2): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service; tidak ada logic di Blade/Livewire view.

| Service | Tanggung jawab |
|---|---|
| `RegistrationService` | `submit()` (→ pending), `withdraw()` milik sendiri, `review()` (under_review → accepted/rejected/waitlisted/cancelled), `bulkReview()` (satu transaction banyak ID), `cancel()` oleh organizer (melepas quota via QuotaService); satu-satunya penulis `status` registration |
| `QuotaService` | Satu-satunya penulis `accepted_count`: `accept(role)` (lock `FOR UPDATE`, cek `accepted_count < quota`, increment; penuh → `QuotaFullException`) dan `release(role)` (decrement, tidak pernah negatif) |
| `AuditLogService` / `SecurityService` | Dipakai ulang dari Phase 1 (kolom `event_id` di `audit_logs` terisi) |

Model otorisasi (ARCHITECTURE.md §4, SECURITY.md §3): permission Spatie = **kemampuan**; `organization_members.role` + membership = **cakupan**. Setiap cek = `user->can('...')` **dan** `$user->belongsToOrganization($event->organization_id)`. Owner mendapat seluruh permission registration via sinkronisasi di `MembershipService` (tambah ke `GRANULAR`/`OWNER_PERMS`); `STAFF_BASE` tetap view-only.

Permission granular baru: `registration.read` (lihat daftar, SECURITY.md §5), `registration.review` (accept/reject/waitlist/bulk). Volunteer own-only via `user_id = auth()->id()` server-side. Konsisten dengan SECURITY.md §5 (`registration.read` untuk staff; review untuk staff ber-permission).

Kebijakan auth di atas Phase 1–2: `verified` middleware tetap berlaku pada group volunteer; submit registration + bulk di-throttle anti-spam (throttlenioskala Phase 1–2: submit 10/menit per user, bulk 10/menit).

## 2. Data & Migrasi

Tabel baru (selain bawaan Phase 0–2):

- **`volunteer_profiles`** — `user_id` (unique, satu profil per akun), `full_name`, `phone`, `city`, `education`, `experience`, `skills` (jsonb), `portfolio_url`, `social_links` (jsonb), `availability` (jsonb), `visibility` (check: `public`, `organizers_only`, `private`; default `organizers_only`), `date_of_birth` + `emergency_contact` (nullable — data minimization, hanya diakses bila event mensyaratkan via policy/presenter terpisah), soft deletes.
- **`event_custom_fields`** — `event_id` (FK cascade), `label`, `type` (check: 13 tipe PRD §7 — text, textarea, email, phone, number, date, time, select, multi-select, radio, checkbox, url, file), `required` (bool), `placeholder` (nullable), `validation_rule` (nullable), `sort_order`, `is_active` (default true). Index: `(event_id, sort_order)`.
- **`event_custom_field_options`** — `field_id` (FK cascade), `label`, `value`, `sort_order`. Hanya untuk select/multi-select/radio/checkbox.
- **`registrations`** — `user_id`, `event_id`, `role_id` (FK cascade), `status` (check: `pending`, `under_review`, `accepted`, `rejected`, `waitlisted`, `cancelled`, `withdrawn`), `submitted_at`, `reviewed_by → users` (nullable), `reviewed_at` (nullable), `rejection_reason` (nullable), `idempotency_key` (unique, UUID dari client per submit). **Unique parsial** `UNIQUE(user_id, event_id) WHERE status IN ('pending','under_review','accepted','waitlisted')` — satu registration aktif per event (PRD aturan 1). Index: `(event_id, status)`, `(role_id, status)`, `(user_id)`.
- **`registration_answers`** — `registration_id`, `field_id` (FK cascade), `value_text`, `value_jsonb` (untuk multi/file), `file_path` (bila tipe file). Unique `(registration_id, field_id)`.
- **`registration_status_histories`** — `registration_id`, `from_status`, `to_status`, `changed_by → users`, `reason`, `created_at`. Append-only (tanpa update/delete oleh aplikasi).

Relasi Eloquent: `User hasOne VolunteerProfile + hasMany Registration`; `Event hasMany Registration + hasMany CustomField`; `EventRole hasMany Registration`; `Registration belongsTo User+Event+Role + hasMany Answer/History`; `CustomField belongsTo Event + hasMany Option`.

Kolom `user_id`, `event_id`, `role_id`, `status` **tidak fillable** — di-set server-side (`status` hanya via `RegistrationService`).

Jawaban custom fields divalidasi backend per tipe: required vs opsional; select/radio/checkbox/multi-select dicocokkan ke options milik field dalam event yang sama (bukan string bebas); email/url/phone/number/date/time divalidasi format; file ikut SECURITY.md §9 penuh (MIME + ekstensi + signature header tervalidasi server, batas ukuran, nama file acak UUID + ekstensi allowlist, direktori non-executable via `Storage`, penolakan tercatat di `security_logs`). Satu transaction: create registration + answers + history + audit; gagal satu → rollback penuh.

## 3. Alur Request & Otorisasi

Route groups (`routes/web.php`, menempel pola Phase 1–2):

- **Volunteer** (auth + verified) — profil milik sendiri (`GET/PATCH /profile/volunteer` atau setara); `GET /registrations` (daftar milik sendiri) + `GET /registrations/{registration}` (detail milik sendiri, `user_id = auth()->id()` server-side, luar milik → **404**); `POST /events/{eventPublic}/register` (pilih role + isi form custom; event non-publik → 404 via binding Task 4; throttle submit) + `POST /registrations/{registration}/withdraw` (milik sendiri saja). Binding scoped: registration milik user + event publik.
- **`/organizer/{organization}/events/{event}/registrations`** — index (filter status + pagination) + show + `POST .../{registration}/review` (aksi satuan: accept/reject/waitlisted/cancelled + alasan wajib untuk reject) + `POST .../bulk-review` (daftar ID + aksi tunggal + alasan; maks 50 ID; satu ID luar scope → seluruh bulk **404** fail-closed). Binding scoped rangkap tiga: `{organization}` milik user (Phase 1), `{event}` dalam org (Phase 2), `{registration}` dalam event; di luar scope → **404**. Policy: `RegistrationPolicy` (lihat: member + `registration.read`; review/bulk: member + `registration.review`; withdraw: volunteer own-only).
- **`/admin`** — daftar semua registration lintas org (paginasi, read-only; konsisten trap Phase 2: param `{registrationAdmin}` by-id global, bukan binding scoped). Tanpa aksi mutasi admin (seleksi milik organizer).

State machine (PRD §8 persis): `pending → under_review/cancelled/withdrawn`; `under_review → accepted/rejected/waitlisted/cancelled/withdrawn`; `waitlisted → accepted (manual, slot tersedia)/rejected/cancelled/withdrawn`; `accepted → cancelled` (oleh organizer, melepas quota via `QuotaService::release`); `rejected/cancelled/withdrawn` terminal. Volunteer hanya boleh: submit (→ pending), withdraw milik sendiri. Waitlist murni manual — tidak ada auto-promosi saat slot bebas.

IDOR (SECURITY.md §4): resource tenant di luar scope → 404; aksi yang resource-nya terlihat namun tidak diizinkan → 403. Konsisten per resource.

Review satuan via `RegistrationService::review()` — satu `DB::transaction`: lock via `QuotaService::accept()` (hanya jalur accepted), update status + `reviewed_by/at` + `rejection_reason` (wajib bila rejected), tulis history append-only, audit. Accept saat event `cancelled`/`archived` ditolak (422); accept saat quota penuh → `QuotaFullException` → 422 "kuota penuh" (organizer lalu waitlist manual). Bulk: satu transaction, tiap item di-lock berurutan (`FOR UPDATE` per role); accept kehabisan slot tengah jalan → seluruh bulk rollback + 422 (tidak ada bulk setengah jalan).

Transaksi wajib (`DB::transaction`): submit + answers + history + audit; review/bulk + quota + history + audit; withdraw/cancel + release + history + audit; create/update custom field + options + audit.

Semua input via Form Request; `$fillable` allowlist eksplisit; `user_id`, `event_id`, `role_id`, status sensitif tidak pernah fillable.

## 4. Testing & Gates

Mengikuti TESTING.md, scope Phase 3:

- **Unit** — `QuotaService`: accept saat slot tersedia → `accepted_count++`; accept saat penuh → exception + waitlist; release → counter turun, tidak pernah negatif (check constraint). `RegistrationService`: transition valid lolos, invalid ditolak; volunteer tidak bisa set Accepted; duplikat aktif ditolak. Validasi custom fields per tipe (option di luar event ditolak, file malicious ditolak). Policy unit per resource (pemilik scope lolos, luar scope gagal).
- **Feature** — submit → under_review → accepted/rejected/waitlisted; withdraw milik sendiri; bulk accept/reject; custom fields end-to-end (13 tipe + file); idempotency double-submit (key sama → record existing); profil create/update + visibility.
- **Authorization** — matriks per endpoint: guest → redirect login (volunteer); staff tanpa `registration.review` → 403; staff beda org → 404; volunteer → 403 atas registration milik orang lain, 200 milik sendiri; non-admin di `/admin/registrations` → 403.
- **Isolation** — Org A vs Org B simetris atas profiles/registrations/answers/fields (404/403 tanpa bocor); katalog publik tidak membocorkan jawaban.
- **Concurrency** — quota: role tersisa 1 slot, 3 user submit+accept paralel → tepat 1 Accepted, 2 Waitlisted/gagal; `accepted_count ≤ quota` selalu. Duplikat: double-submit (idempotency key sama) → 1 record; accept ganda → counter naik 1x. Cancellation bersamaan dengan accept → state konsisten. Syarat: melawan PostgreSQL (bukan SQLite) agar `FOR UPDATE` dan constraint parsial benar-benar teruji.
- **Negatif** — mass-assignment (`user_id`, `event_id`, `status` via request ditolak); registrasi saat event draft/cancelled/archived, periode tutup, user/organizer suspended; event non-publik → 404; file oversize/malicious ditolak; bulk lintas event → 404; bulk > 50 ID → 422.
- **Gates** — Pest hijau; Pint PASS; PHPStan/Larastan level 5 No errors; `composer audit` bersih critical/high.

## 5. Self-Review

1. **Placeholder scan:** seluruh tabel/kolom/status/permission bernama eksplisit; batas numerik konkret (bulk maks 50 ID, throttle submit 10/menit, 13 tipe field, 7 status registration). Tanpa TBD/TODO.
2. **Konsistensi internal:** "waitlist manual" (§1, §3) konsisten dengan non-scope "auto-promosi" (§5 header); `QuotaService` satu-satunya penulis `accepted_count` (§1) konsisten dengan DATABASE.md §3 (`lockForUpdate()` di `QuotaService`); unique parsial (§2) konsisten dengan PRD aturan 1; bulk fail-closed (§3) konsisten dengan IDOR Phase 2.
3. **Scope:** murni volunteer + registration + seleksi + quota; assignment/attendance (Phase 4), export (Phase 6) eksplisit non-scope. Dua service baru (`RegistrationService`, `QuotaService`) — pemisahan lock dari logika review adalah pertahanan T11.
4. **Ambiguitas:** `role_id` wajib di registration (volunteer selalu pilih role — keputusan eksplisit, bukan nullable seperti shift); `rejection_reason` wajib hanya untuk rejected; admin read-only tanpa mutasi (seleksi milik organizer); visibility default `organizers_only` (private-by-default, SECURITY.md §11).
