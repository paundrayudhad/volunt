# SECURITY

Prinsip: **tidak ada klaim "100% aman"**. Secure-by-design → automated
testing → security review → hardening → monitoring → continuous improvement.

Aturan absolut (lihat README): jangan percaya input/role/tenant ID/event ID
dari client; otorisasi selalu backend; resource selalu ter-scope ke tenant.

## 1. Authentication

- Laravel authentication bawaan (starter kit sesuai kebutuhan —
  diputuskan saat scaffold Phase 0): register, login, logout, verifikasi email,
  forgot/reset password, session expiration + invalidasi.
- Password: hashing bcrypt/argon2, tidak plaintext/reversibel, tidak masuk
  log/response. Kebijakan panjang minimum disarankan 12 karakter
  (dikunci saat Phase 1, validasi backend).
- Login: rate limit 5/menit per IP+email, lockout temporer, respons
  generik (tidak membocorkan email terdaftar), setiap gagal tercatat di
  `security_logs`.
- Operasi sensitif (ubah permission, suspend, hapus organisasi, export
  sensitif): re-authentication + konfirmasi + alasan teraudit.
- Sanctum hanya saat API eksternal dibangun (lihat [API.md](API.md)).

## 2. Session & Cookie

`Secure + HttpOnly + SameSite=Lax`, rotasi ID session saat login
(`Session::regenerate()`), invalidasi + regenerate token CSRF saat logout,
idle timeout + absolute lifetime configurable. Tanpa token auth di
`localStorage`.

## 3. Authorization: RBAC + Membership + Scope

- Role global: hanya `super_admin`.
- Owner: penuh dalam 1 organisasi. Staff: permission configurable.
- Setiap cek = permission **dan** scope:
  `user->can('registration.review') &&
  $user->belongsToOrganization($event->organization_id)`.
- Policy per resource (`EventPolicy`, `RegistrationPolicy`,
  `AssignmentPolicy`, `AttendancePolicy`, `IncidentPolicy`, ...).
  Route model binding scoped ke organisasi/event user.
- Mass assignment: `$fillable` allowlist eksplisit; field sensitif
  (`organization_id`, `owner_id`, `role`, `permissions`, status verifikasi,
  security flags) tidak pernah fillable — di-set server-side.
- Privilege escalation: tidak ada endpoint yang membiarkan user mengubah
  role/organisasi/permission milik sendiri.

## 4. Tenant Isolation & IDOR

Rantai: `User → Organization → Event → Resource`. Setiap baca/tulis
memvalidasi ownership chain via scoped query + policy.

- IDOR: `GET /organizer/events/124` di luar scope → **404** (tidak
  membocorkan keberadaan) untuk resource tenant; 403 untuk aksi yang
  resource-nya terlihat namun tidak diizinkan. Konsisten per resource.
- Wajib diuji pada: events, divisions, roles, shifts, registrations,
  assignments, attendance, incidents, certificates, exports, announcements.
- Frontend (hidden button, filter) bukan boundary keamanan.

## 5. Authorization Matrix

| Aksi | Super Admin | Owner | Staff | Volunteer | Guest |
|---|---|---|---|---|---|
| Kelola organization | ✓ | own org | — | — | — |
| Kelola member org | ✓ | own org | sesuai perm | — | — |
| Create event | ✓ | ✓ | `event.create` | — | — |
| Edit event | ✓ | ✓ | `event.update` | — | — |
| Delete/cancel event | ✓ | ✓ | — | — | — |
| Lihat registration | ✓ | ✓ | `registration.read` | milik sendiri | — |
| Accept/reject/waitlist | ✓ | ✓ | `registration.review` | — | — |
| Assign/reassign | ✓ | ✓ | `volunteer.assign` | — | — |
| Check-in/out manual (operator) | ✓ | ✓ | `attendance.manage` | — | — |
| Check-in/out milik sendiri (QR) | — | — | — | own only | — |
| Lihat attendance event | ✓ | ✓ | `attendance.read` | milik sendiri | — |
| Buat announcement | ✓ | ✓ | `announcement.create` | — | — |
| Export | ✓ | ✓ | `report.export` | sertifikat sendiri | — |
| Lihat audit log | ✓ | own org | — | — | — |
| Lihat security log | ✓ | — | — | — | — |
| Suspend user/org | ✓ | — | — | — | — |
| Lihat event publik | ✓ | ✓ | ✓ | ✓ | ✓ |

"✓ Owner/Staff" selalu berarti **dalam organisasinya sendiri** —
di luar itu 404/403. Volunteer "own only" ditegakkan via
`user_id = auth()->id()` server-side.

## 6. Input, XSS, SQLi

- Semua input via Form Request (`authorize()` + `rules()`); jangan bangun
  SQL dari input — Eloquent/Query Builder + binding; raw SQL hanya dengan
  parameter ter-bind.
- Output Blade default `{{ }}` (escaped). Tidak ada `{!! !!}` untuk konten
  user. Rich text (bila dibutuhkan): sanitizer allowlist — strip `<script>`,
  event handler, skema URL (`javascript:`, `data:`, `file:`).
- Validasi file custom-field tipe file/URL mengikuti §9.

## 7. CSRF & Rate Limit

- Semua route state-changing (POST/PUT/PATCH/DELETE) di bawah middleware
  web + verifikasi CSRF; diuji pada: create/update event, registration,
  accept/reject, assignment, attendance.
- Rate limit (throttle): login, register, password reset, verifikasi,
  submit registration, QR scan, export, broadcast notifikasi, API sensitif.
  Pelanggaran tercatat di `security_logs`.

## 8. QR Attendance Anti-Fraud

- Token = Laravel signed URL / encrypted payload: `assignment_id`,
  `shift_id`, `exp` (±5 menit), `nonce` sekali pakai (cache/database).
- Validasi server: signature, expiry, nonce belum dipakai, event +
  assignment + shift cocok, dalam time window, `user_id` dari auth context
  (bukan dari QR/client). Tanpa secret plaintext di QR.
- Check-in/out idempotent via `idempotency_key`; replay → 409 + log.

## 9. File Upload

MIME + ekstensi + **signature header** tervalidasi server, batas ukuran,
nama file acak (UUID + ekstensi allowlist), direktori non-executable di
luar document root (via `Storage`), tolak path traversal & overwrite,
image di-re-encode bila memungkinkan. Jangan percaya original filename,
ekstensi, atau MIME dari client. Penolakan tercatat di `security_logs`.

## 10. SSRF, Open Redirect, Headers, Error

- **SSRF**: tidak ada fetch URL arbitrary dari user. Bila dibutuhkan
  (mis. unfurl sponsor): allowlist domain + protokol https, blokir
  localhost/private IP/metadata endpoint, batasi redirect & timeout,
  validasi hasil resolusi DNS.
- **Open redirect**: hanya route internal / domain allowlist; parameter
  `redirect`/`next` divalidasi, skema non-http(s) ditolak.
- **Headers produksi**: HSTS, CSP (ketat, disesuaikan asset), nosniff,
  Referrer-Policy, Permissions-Policy, frame-deny, cookie Secure.
- **Error**: respons generik + internal error ID + server-side logging.
  Tidak ada stack trace, `SQLSTATE`, path, env, secret di produksi.
  Handler: 401/403/404/419/422/429/500.

## 11. Privacy & Data Minimization

Profil volunteer private-by-default (`visibility`); phone, email pribadi,
emergency contact, catatan internal/review hanya untuk role yang
membutuhkan (policy + presenter/API resource terpisah public vs private).
Kumpulkan data seminimal mungkin; retensi/deletion configurable.

## 12. Audit Log & Security Log

- **Audit** (`audit_logs`, append-only): actor, organization, event,
  action, resource type+ID, old/new values, IP, UA, timestamp, request ID.
  Mencakup: login/logout, CRUD event/division/role, registration
  created/accepted/rejected, assignment created/updated, attendance,
  export, perubahan role/permission. Tidak boleh diubah organizer;
  tanpa password/token/secret.
- **Security** (`security_logs`, append-only, terpisah): failed_login,
  account_locked, invalid_token, forbidden_access, rate_limit_exceeded,
  csrf_violation, suspicious_request, file_upload_rejected.
- Semua request membawa Request ID (middleware + log context).

## 13. Threat Model

Format: Threat → Impact → Likelihood → Mitigasi → Test.

| # | Threat | Impact | Likelihood | Mitigasi | Test |
|---|---|---|---|---|---|
| T1 | Broken Access Control / IDOR | Tinggi — baca/ubah data tenant lain | Tinggi | Scoped query + Policy + binding scoped; 404 di luar scope | Tenant isolation suite (§14), tiap resource |
| T2 | Tenant Data Leakage (export/report) | Tinggi | Sedang | Export selalu scoped + permission + audit + rate limit | Export lintas tenant ditolak; audit tercatat |
| T3 | Privilege Escalation | Tinggi | Sedang | Fillable allowlist; tanpa endpoint ubah role sendiri; re-auth aksi sensitif | Mass-assignment & escalation test |
| T4 | Credential Theft / Stuffing | Tinggi | Tinggi | Hash kuat, lockout, session rotation, cookie aman | Brute-force & session test |
| T5 | Brute Force (login/reset/OTP) | Sedang | Tinggi | Throttle + lockout + monitoring, respons generik | Rate-limit test |
| T6 | Stored/Reflected XSS | Sedang–Tinggi | Sedang | Escape default, sanitizer allowlist | XSS payload test |
| T7 | CSRF | Sedang | Sedang | Token + SameSite, semua mutasi terproteksi | CSRF test per aksi kritis |
| T8 | SQL Injection | Tinggi | Rendah | ORM/binding; raw SQL ter-bind | SAST + payload test |
| T9 | SSRF | Sedang | Rendah | Allowlist domain, blokir internal/metadata | SSRF test bila fetch eksternal ada |
| T10 | File Upload Abuse | Tinggi | Sedang | Validasi signature, nama acak, non-executable | Upload malicious/oversize test |
| T11 | Race Condition (quota/attendance) | Tinggi | Sedang | Transaction + `FOR UPDATE` + check constraint | Concurrency test (3 user, 1 slot) |
| T12 | Session Hijacking | Tinggi | Rendah | Secure/HttpOnly/SameSite, rotasi, expiry | Session invalidation test |
| T13 | API Abuse | Sedang | Sedang | Auth, throttle, pagination, scoping | API abuse test |
| T14 | Information Disclosure (error/log) | Sedang | Sedang | Error generik + ID, tanpa secret di log | Error-response review |
| T15 | Open Redirect | Rendah–Sedang | Rendah | Allowlist redirect internal | Open-redirect test |

## 14. Security Testing Wajib (acuan TESTING.md)

IDOR, tenant isolation (Org A vs Org B), privilege escalation, mass
assignment, SQLi, XSS, CSRF, SSRF, open redirect, file upload, brute
force, rate limit, session invalidation, race condition, duplicate
registration, information disclosure. Jangan hanya happy path.
