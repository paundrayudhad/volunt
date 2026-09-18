# Phase 4 (Operations) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–4) pada 2026-09-18.
**Scope:** assignment organizer ke shift, schedule volunteer, QR attendance dinamis (organizer memindai QR volunteer), announcement terscope + notifikasi in-app. (PRD §9.3–9.7, §11; DATABASE.md §Operations/Komunikasi)
**Non-scope:** artist liaison, incident, lost & found, talent pool, certificate (Phase 5); export/analytics/dashboard (Phase 6); kanal WA/email/push beneran (non-goal PRD — hanya kolom preferensi + arsitektur siap); QR statis; self-service klaim shift oleh volunteer; auto-promosi waitlist (tetap manual Phase 3).

Keputusan kunci (hasil klarifikasi 2026-09-18): QR dinamis per volunteer (token unik, short-lived, sekali pakai, rotasi); assignment manual oleh organizer (validasi kuota shift + konflik waktu); announcement + notifikasi in-app database (broadcast via queue); shift Phase 2 sebagai unit schedule; arah scan organizer-memindai-volunteer; reassign = assignment sama pindah shift (status `reassigned`, kuota lama dilepas + baru diisi).

## 1. Arsitektur & Komponen

Modul domain baru **`Operations`**, menempel pada rantai Phase 3: `Registration (accepted) → Assignment → Attendance`, schedule = shift Phase 2.

Alur lapisan (sama seperti Phase 1–3): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service.

| Service | Tanggung jawab |
|---|---|
| `AssignmentService` | Satu-satunya penulis `status` assignment: `assign()`, `reassign()`, `confirm()`, `cancel()`, `bulkAssign()`. Validasi: registration `accepted`; shift satu event dengan registration; kuota shift via `lockForUpdate` + counter `filled_count` (pola `QuotaService` Phase 3); konflik waktu (volunteer sama tak boleh dua shift overlap). History append-only + audit, satu transaction |
| `AttendanceService` | Satu-satunya penulis check-in/out: validasi token + assignment aktif + shift + jendela waktu + user dari auth context. Tulis `attendances` + `attendance_logs` append-only + audit |
| `AnnouncementService` (tipis) | Buat/terbit pengumuman terscope; saat terbit → dispatch queue job yang menulis notifikasi in-app per penerima (HTTP tidak menunggu broadcast) |

Model otorisasi (pola Phase 1–3): permission Spatie = **kemampuan**; membership = **cakupan** (`can()` + `belongsToOrganization()`). Owner mendapat seluruh permission operations via sinkronisasi `MembershipService`; `STAFF_BASE` tetap view-only.

Permission granular baru: `assignment.manage`, `attendance.record`, `announcement.publish` (mutasi); varian `.read` masing-masing untuk view-only. Volunteer own-only via `user_id = auth()->id()` server-side.

Throttle: scan QR 30/menit; bulk assign 10/menit (konsisten Phase 3); `password.confirm` pada assign/bulk (aksi sensitif, konsisten Phase 2–3).

## 2. Data & Migrasi

Tabel baru (kolom mengikuti DATABASE.md §Operations/Komunikasi):

- **`assignments`** — `registration_id` (unique, satu aktif per registration), `user_id`, `event_id`, `division_id`, `role_id`, `shift_id`, `location`, `supervisor_id → users`, `status` (check: `assigned`, `reassigned`, `confirmed`, `completed`, `cancelled`), soft deletes. Index: `(event_id, status)`, `(user_id, event_id)`, `(shift_id)`.
- **`assignment_histories`** — `assignment_id`, `from_status`, `to_status`, `changed_by → users`, `note`, `created_at`. Append-only.
- **`event_shifts` counter** — tambah `filled_count` (default 0) + check `filled_count >= 0`, `filled_count <= capacity`; ditulis hanya via `AssignmentService` dengan `lockForUpdate` (pola `accepted_count` Phase 3).
- **`attendances`** — `assignment_id`, `shift_id`, `event_id`, `user_id`, `checked_in_at`, `checked_out_at`, `method` (check: `qr`, `manual`), `status` (check: `present`, `late`, `absent`), `idempotency_key` (unique). Unique `(assignment_id, shift_id)`. Index: `(event_id, checked_in_at)`, `(user_id, event_id)`.
- **`attendance_logs`** — `attendance_id`, `action` (check: `check_in`, `check_out`, `void`), `actor_id → users`, `ip`, `user_agent`, `created_at`. Append-only.
- **`qr_tokens`** (baru, usulan desain ini) — `assignment_id`, `token_hash` (sha256; token mentah hanya tampil sekali saat dibuat/dirotasi), `expires_at` (short-lived, 5 menit), `used_at` (nullable; sekali pakai per aksi), `revoked_at` (nullable; rotasi mencabut token lama). Index: `(token_hash)` unique.
- **`announcements`** — `event_id`, `author_id → users`, `target_type` (check: `event`, `division`, `role`, `shift`, `individual`), `target_id` (nullable), `title`, `body`, `published_at` (null = draft), `expires_at` (nullable). Index: `(event_id, published_at)`.
- **`notifications`** — standar Laravel (`notifiable_type/id` polimorfik, `data` jsonb, `read_at` nullable).
- **`users` preferensi** — tambah `notification_preferences` (jsonb, nullable): kesiapan kanal luar, belum dipakai pengiriman (non-goal).

Relasi: `Registration hasOne Assignment`; `EventShift hasMany Assignment`; `Assignment belongsTo Registration+User+Event+Shift + hasMany History/Attendance/QrToken`; `Announcement belongsTo Event+Author`.

Kolom sensitif (`user_id`, `event_id`, `status`, counter) tidak fillable — via service.

Aturan QR (PRD §9.4 — token signed, short-lived, anti-replay): token acak 32 byte (hex), disimpan sebagai sha256; expiry 5 menit; sekali pakai (`used_at`); rotasi via endpoint volunteer (mencabut lama); validasi jendela shift di service.

## 3. Alur Request & Otorisasi

Route groups (pola Phase 1–3):

- **Organizer** `/organizer/{organization}/events/{event}/assignments` — index (filter status/shift, paginasi) + show + `POST .../assign` (registration accepted + shift) + `POST .../{assignment}/confirm|reassign|cancel` + `POST .../bulk-assign` (maks 50, satu transaction all-or-nothing, fail-closed 404). Binding scoped rangkap tiga (org→event→assignment); luar scope → 404. Policy: member + `assignment.manage`.
- **Organizer scan** `POST .../attendances/scan` (`qr_token` + `action: check_in|check_out` + idempotency key; throttle 30/menit) + `POST .../attendances/manual` (`method=manual` + alasan wajib; permission sama). Policy: member + `attendance.record`.
- **Volunteer** (auth + verified, own-only, luar milik → 404): `GET /my/schedule` (jadwal per event + status assignment), `GET /my/qr` (tampilkan QR aktif; `POST /my/qr/rotate` untuk rotasi), `GET /announcements` (hanya published + belum expired + menarget saya), `GET /notifications` + `POST /notifications/{id}/read`.
- **Admin** — daftar assignments/attendances/announcements lintas org, read-only (pola Phase 2–3: param global by-id, tanpa mutasi).

State machine assignment: `assigned → confirmed/completed/cancelled`; `confirmed → completed/cancelled`; `reassigned` (hasil reassign: assignment sama pindah shift; kuota lama dilepas, baru diisi; history catat shift lama→baru); `completed/cancelled` terminal. Hanya registration `accepted` yang bisa di-assign.

Alur scan: lookup `token_hash` → cek expiry/bekas/revoked → assignment aktif → shift satu event → jendela waktu (`start_at − 30 mnt` s/d `end_at`; di luar → 422) → tulis attendance + log + audit. `late` bila check-in lewat `start_at + 15 mnt`. Check-out wajib setelah check-in; check-in dan check-out memakai token berbeda (sekali pakai per aksi). Replay token sama → 422. Token orang/event lain → 404.

Broadcast pengumuman: terbit → queue job resolve penerima dari target (`event` = semua volunteer accepted event; `division`/`role`/`shift` = filter assignment; `individual` = satu user) → tulis baris `notifications` per penerima. HTTP kembali langsung (PRD §9.7). Draft (`published_at` null) tak terlihat volunteer.

IDOR (SECURITY.md §4): tenant di luar scope → 404; aksi terlihat tapi tak diizinkan → 403.

Transaksi wajib (`DB::transaction`): assign/reassign/cancel + kuota shift + history + audit; scan/manual + attendance + log + audit; terbit pengumuman + dispatch job.

Semua input via Form Request; `$fillable` allowlist; counter dan status tak pernah fillable.

## 4. Testing & Gates

TDD per task (helper prefix per file):

- **Assignment**: assign valid → `assigned` + `filled_count` +1; shift penuh → 422 + rollback; overlap waktu volunteer sama → 422; registration non-accepted → 422; shift beda event → 422/404; bulk 50 all-or-nothing (gagal tengah → rollback penuh); reassign melepas kuota lama + mengisi baru + history; confirm → `confirmed`.
- **Attendance/QR**: check-in dalam jendela → `present`; lewat ambang → `late`; luar jendela → 422; token expired/bekas/revoked → 422; replay → 422; token asing → 404; check-out tanpa check-in → 422; manual tercatat + alasan wajib.
- **Announcement/notifikasi**: draft tak terlihat; terbit → penerima tepat per target + non-target `assertDontSee`; notifikasi tertulis via queue (fake di test); `read` milik sendiri; expired tak tampil.
- **Otorisasi** (gaya `RegistrationAuthorizationTest`): staff read-only 403 di semua mutasi; lintas org/event 404; guest → login; volunteer own-only.
- **Isolasi** (gaya `RegistrationIsolationTest`): dua org independen, tak ada rembesan data.
- **Konkurensi**: rebutan slot terakhir shift (pola fork Phase 3) — satu menang, sisanya 422, `filled_count` tak pernah > capacity.

Gates (konsisten Phase 1–3): Pest hijau penuh; Pint; Larastan level 5; `composer audit` bersih; prose Indonesia + identifier English; konten nyata tanpa placeholder; `git add` eksplisit; commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`; controller tipis → Form Request → Service → Model; Docker via `docker compose exec app`.
