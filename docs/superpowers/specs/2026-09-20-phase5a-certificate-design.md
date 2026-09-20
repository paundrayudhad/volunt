# Phase 5A (Certificate) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–4) pada 2026-09-20.
**Scope:** penerbitan sertifikat volunteer pasca-event: eligibility ambang kehadiran per event, batch terbitkan organizer, PDF unduhan own-only, verifikasi publik minimal, revoke, penutupan assignment → `completed`. (PRD §9.5, §11 Phase 5; DATABASE.md §Kredensial & Logging; SECURITY.md §5; TESTING.md §2–§8)
**Non-scope:** artist liaison (5C), incident + lost & found (5B), talent pool (5D), export/analytics/dashboard (Phase 6); template visual kustom per event; kirim sertifikat via email/WA (non-goal PRD); tanda tangan digital/QR-crypto; analitik unduhan.

Keputusan kunci (hasil klarifikasi 2026-09-20): Phase 5 dipecah 5A–5D (5D terakhir karena butuh history lintas event); eligibility = ambang% kehadiran per event (kolom `events.certificate_min_attendance_pct`, default 50) + lantai minimal 1 kehadiran, penyebut = assignment aktif dengan shift yang masih ada (EventShift tanpa SoftDeletes: penghapusan shift merambat via FK cascade + guard `whereHas('shift')` sebagai kontrak); penerbitan = batch manual organizer (idempoten, re-run aman; kandidat anomali dilewati per-baris tanpa menggugurkan batch); assignment aktif yang shift-nya lewat ditutup → `completed` saat batch (via `AssignmentService::complete()` baru — menutup utang non-scope Phase 4); artefak = PDF via dompdf, generate-on-download; verifikasi publik data minimal via `certificate_no`.

## 1. Arsitektur & Komponen

Modul domain baru **`Certificate`**, menempel pada rantai Phase 4: `Assignment (completed) → Attendance → Certificate`. `CertificateService` satu-satunya penulis `certificates` (pola `AssignmentService`/`AttendanceService`).

Alur lapisan (sama seperti Phase 1–4): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service.

| Service / Job | Tanggung jawab |
|---|---|
| `CertificateService` | Satu-satunya penulis sertifikat: `isEligible()`, `issueBatch()`, `issueIndividual()`, `revoke()`. Eligibility: event `completed`/`archived` + ≥1 attendance `present`/`late` + `hadir/penyebut ≥ ambang`. Penyebut = assignment AKTIF (`Assignment::ACTIVE`) dengan shift yang masih ada. Satu transaction + lock event |
| `AssignmentService::complete()` (baru) | Menutup assignment aktif → `completed` (edge `assigned/reassigned/confirmed → completed` yang selama ini dideklarasikan di TRANSITIONS tapi tak terkendarai). Hanya assignment yang shift-nya sudah lewat. History append-only + audit |
| Job batch besar (bila penerima ≥50) | Pola broadcast Phase 4: HTTP kembali langsung, penerbitan via queue. Di bawah 50 → sinkron dalam request |

Model otorisasi (pola Phase 1–4): permission Spatie = **kemampuan**; membership = **cakupan** (`can()` + `belongsToOrganization()`). Owner mendapat permission certificate via sinkronisasi `MembershipService`; `STAFF_BASE` tetap view-only.

Permission granular baru: `certificate.issue`, `certificate.revoke` (mutasi); `certificate.read` (view-only). Volunteer own-only via `user_id = auth()->id()` server-side. Verifikasi publik tanpa auth (token acak 64 hex sebagai capability).

Throttle: issue batch 10/menit (konsisten bulk Phase 3–4); verifikasi publik 60/menit anti-scrape; `password.confirm` pada issue + revoke (aksi sensitif).

## 2. Data & Migrasi

Tabel baru (kolom mengikuti DATABASE.md §Kredensial & Logging):

- **`certificates`** — `event_id`, `user_id`, `registration_id` (unique), `certificate_no` (unique, format `WV-2026-XXXXXX`), `issued_at`, `revoked_at` (nullable), `revoke_reason` (nullable), `qr_token_hash` (unique, token acak 64 hex di-hash sha256). Unique `(event_id, user_id)` (jaring pengaman idempotensi). Index: `(event_id, revoked_at)`, `(user_id)`.
- **`certificate_verifications`** — `certificate_id`, `verified_at`, `ip`, `user_agent`. Append-only (jejak verifikasi publik). Kunjungan token-asing tidak dicatat (hindari log poisoning).
- **`events` kolom baru** — `certificate_min_attendance_pct` (integer nullable; null = default sistem 50; validasi Form Request 1–100 + check constraint via `DB::statement`). Constant `CertificateService::DEFAULT_THRESHOLD = 50`.

Relasi: `Event hasMany Certificate`; `User hasMany Certificate`; `Registration hasOne Certificate`; `Certificate hasMany CertificateVerification`.

Kolom sensitif (`event_id`, `user_id`, `certificate_no`, `qr_token_hash`, counter) tidak fillable — via service.

Aturan nomor: `certificate_no` dibuat acak (`WV-{tahun}-{6 alnum}`); collision → retry di loop; unique constraint DB sebagai arbiter terakhir (pola idempotency Phase 3–4).

## 3. Alur Request & Otorisasi

Route groups (pola Phase 1–4):

- **Organizer** `/organizer/{organization}/events/{event}/certificates` — index (paginasi; counter "diterbitkan" = non-revoked) + show (termasuk riwayat verifikasi read-only) + `POST .../issue` (batch; opsional field `min_attendance_pct` di form yang sama) + `POST .../{certificate}/revoke` (alasan wajib min 10 karakter). Filter index valid/dicabut: DEFERRED ke Phase 6/5D. Binding scoped rangkap tiga (org→event→certificate); luar scope → 404. Policy: member + `certificate.issue` / `certificate.revoke`.
- **Volunteer** (auth + verified, own-only, luar milik → 404): `GET /my/certificates` (daftar + status, paginasi), `GET /my/certificates/{id}/download` (stream PDF; sertifikat dicabut → 422 'Sertifikat ini telah dicabut.').
- **Publik** (tanpa auth): `GET /verify/certificate/{token}` — nomor + nama volunteer + nama event + tanggal terbit + VALID/DICABUT; token tak dikenal → 404; `noindex` meta.
- **Admin** — daftar sertifikat lintas org, read-only (pola Phase 2–4: param global by-id, tanpa mutasi). DEFERRED ke Phase 6/5D.

Alur batch (`issueBatch`): lock event → baca ambang efektif → tutup assignment aktif yang shift-nya lewat → `completed` (via `AssignmentService::complete`) → untuk tiap volunteer layak: lewati bila sudah punya sertifikat aktif (unique `(event_id, user_id)` sebagai jaring) → generate nomor + token + `issued_at` + audit. Kandidat anomali (tanpa registration accepted, user hilang, collision nomor) dilewati per-baris + dicatat di `failed` tanpa menggugurkan batch. Re-run: hanya menerbitkan yang baru layak; nol duplikat, nol error. Revoke bersifat FINAL: `issueIndividual` melempar 422 bila ADA baris apa pun untuk (event, user), aktif maupun dicabut — unique `(event_id, user_id)` melarang terbit-baru sebagai koreksi salah-cabut.

Revoke: `revoked_at` + alasan + audit; **tidak** membuka kembali assignment (penutupan evaluasi final); verifikasi publik menampilkan DICABUT; unduhan → 422.

IDOR (SECURITY.md §4): tenant di luar scope → 404; aksi terlihat tapi tak diizinkan → 403.

Transaksi wajib (`DB::transaction`): issue batch + penutupan assignment + audit; revoke + audit.

Semua input via Form Request; `$fillable` allowlist.

## 4. Artefak PDF

Library: `barryvdh/laravel-dompdf` (versi terkunci di composer) + QR inline PNG (library QR diputuskan saat implementasi; kandidat `bacon/bacon-qr-code`). Template Blade A4 landscape: nama volunteer, nama event + tanggal, nomor sertifikat, nama organizer/org, QR verifikasi, tanggal terbit. Layout siap diganti (non-goal: template kustom per event).

Generate-on-download (tanpa menyimpan file — tanpa permukaan storage + tanpa scheduled cleanup). PDF tidak di-cache lintas user (konsisten ARCHITECTURE.md §6: data privat tidak di-cache).

## 5. Testing & Gates

TDD per task (helper prefix per file):

- **Eligibility**: event belum completed → tak layak; 0 assignment aktif → tak layak; hadir tapi di bawah ambang → tak layak; ambang per event dihormati (custom 1–100); null = default 50; shift soft-deleted tak masuk penyebut; assignment cancelled tak masuk penyebut.
- **Batch**: layak → terbit + nomor unik format benar + assignment tertutup `completed` + history + audit; shift belum lewat tak ditutup; re-run → 0 baru 0 error; double-submit bersamaan → tepat 1 per `(event_id, user_id)`; event non-completed → ditolak.
- **Revoke**: revoke → DICABUT di verifikasi + unduhan 422 + assignment tetap `completed`; batch berikutnya tak menerbitkan ulang; alasan <10 karakter → 422.
- **Unduh/verifikasi**: own-only (milik orang → 404); token asing → 404 tanpa log; kunjungan valid → 1 baris verification; `noindex` hadir.
- **Otorisasi** (gaya `OperationsAuthorizationTest`): staff read-only 403 semua mutasi; lintas org/event 404; guest → login; volunteer own-only.
- **Isolasi** (gaya `RegistrationIsolationTest`): dua org independen, simetris.
- **Konkurensi**: penerbitan ganda bersamaan (pola fork Phase 3–4, skip by design bila pcntl tak tersedia + residual sekuensial) — `certificate_no` tak pernah duplikat.

Gates (konsisten Phase 1–4): Pest hijau penuh; Pint; Larastan level 5; `composer audit` bersih; prose Indonesia + identifier English; konten nyata tanpa placeholder; `git add` eksplisit; commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`; controller tipis → Form Request → Service → Model; Docker via `docker compose exec app`.
