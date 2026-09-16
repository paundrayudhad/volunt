# Phase 1 (Foundation) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–4) pada 2026-09-16.
**Scope:** auth, user, organization, membership, invitation, pengajuan organisasi, RBAC, tenant isolation + test isolasi. (PRD §11)
**Non-scope:** event/division/role/shift (Phase 2), registration (Phase 3), API/Sanctum (ditunda per ARCHITECTURE.md), promosi super_admin via panel, join-request publik.

Keputusan kunci: auth **Breeze Livewire** + kebijakan SECURITY.md; organisasi via **pengajuan + approval Super Admin**; keanggotaan via **undangan ke user terdaftar**; super_admin pertama via **seed artisan**.

## 1. Arsitektur & Komponen

Modular monolith (ARCHITECTURE.md §1): dua modul domain — `Identity` (user, auth, super_admin seed) dan `Tenancy` (organization, membership, invitation, request, RBAC-scope).

Alur lapisan: Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service; tidak ada logic di Blade/Livewire view.

| Service | Tanggung jawab |
|---|---|
| `OrganizationService` | Ajukan organisasi; setujui/tolak pengajuan; suspend/arsip/aktifkan; update profil org |
| `MembershipService` | Undang, terima/tolak undangan, ubah role staff, keluarkan member; satu-satunya penulis `organization_members`; sinkronisasi permission Spatie per-org |
| `AuditLogService` / `SecurityService` | Pencatatan append-only; dipanggil service lain, tidak pernah langsung dari controller |

Kebijakan auth di atas Breeze Livewire (SECURITY.md §1–2): password min 12 char (validasi backend); throttle login 5/menit per IP+email + lockout temporer; respons login generik (tidak membocorkan email terdaftar); setiap gagal login tercatat di `security_logs`; rotasi session ID saat login (`Session::regenerate()`); cookie `Secure + HttpOnly + SameSite=Lax`; invalidasi + regenerate CSRF saat logout.

Model otorisasi (ARCHITECTURE.md §4, SECURITY.md §3): permission Spatie = **kemampuan** (`organization.update`, `member.invite`, `member.remove`, ...); `organization_members.role` (owner/staff) + membership = **cakupan**. Setiap cek = `user->can('...')` **dan** `$user->belongsToOrganization($orgId)`. Owner mendapat seluruh permission org-nya via sinkronisasi di `MembershipService` saat penetapan role — bukan role Spatie per-org — agar `organization_members` tetap satu-satunya sumber kebenaran keanggotaan. Role Spatie global hanya `super_admin`.

## 2. Data & Migrasi

Tabel baru (selain bawaan Breeze — `users`, `password_reset_tokens`, `sessions` — dan Spatie — `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` — yang sudah ada dari Phase 0):

- **`organizations`** — `name`, `slug` (unique global), `logo_path` (nullable), `description` (nullable), `email` (nullable), `phone` (nullable), `website` (nullable), `social_links` (jsonb, nullable), `status` (`active`, `suspended`, `archived`; check constraint), soft deletes. Index: `slug`, `(status)`.
- **`organization_members`** — `organization_id` (FK), `user_id` (FK), `role` (`owner`/`staff`; check constraint), `status` (`active`/`suspended`; check constraint), `joined_at` (timestamptz). Unique `(organization_id, user_id)`. Index `(user_id)`. Kolom `organization_id`, `user_id`, `role` **tidak fillable** — di-set server-side.
- **`organization_invitations`** — `organization_id` (FK), `email`, `role` (hanya `staff`; owner tidak dapat diundang — ownership hanya via pengajuan disetujui atau transfer eksplisit), `token_hash` (unique), `expires_at` (default +7 hari), `accepted_at`/`declined_at` (nullable), `invited_by` (FK users). Unique parsial: satu undangan pending per `(organization_id, email)`.
- **`organization_requests`** (pengajuan) — `user_id` (FK pemohon), `name`, `slug`, `description` (nullable), `contact` (jsonb, nullable), `status` (`pending`, `approved`, `rejected`; check constraint), `reviewed_by` (FK users, nullable), `reviewed_at` (nullable), `rejection_reason` (nullable). Batas: maks 3 pengajuan pending per user (validasi backend + throttle route).
- **`audit_logs`** — `actor_id`, `organization_id` (nullable), `event_id` (nullable, untuk phase berikut), `action`, `resource_type`, `resource_id`, `old_values`/`new_values` (jsonb), `ip`, `user_agent`, `request_id`, `created_at`. Append-only: tanpa update/delete oleh aplikasi. Index: `(actor_id, created_at)`, `(action)`. Tanpa password/token/secret.
- **`security_logs`** — `actor_id` (nullable), `type` (`failed_login`, `account_locked`, `forbidden_access`, `rate_limit_exceeded`, `csrf_violation`, `suspicious_request`, ...; check constraint), `ip`, `user_agent`, `context` (jsonb, tanpa secret), `created_at`. Append-only. Index: `(type, created_at)`, `(ip, created_at)`.

Super admin: role Spatie global `super_admin`, ditetapkan via `php artisan app:seed-super-admin --email=... --name=...` (idempotent — lewati bila sudah ada; teraudit ke `audit_logs`; email diambil dari argumen/ENV, password awal acak + wajib reset). Tidak ada endpoint promosi super_admin di Phase 1.

Relasi Eloquent: `User hasMany OrganizationMember + belongsToMany Organization (via members)`; `Organization hasMany OrganizationMember, OrganizationInvitation, OrganizationRequest (sebagai hasil)`; helper `User::belongsToOrganization($orgId): bool` dan `User::organizationRole($orgId): ?string`.

## 3. Alur Request & Otorisasi

Route groups (`routes/web.php`):

- **Guest** — route Breeze (login, register, reset, verifikasi) + throttle ketat; halaman publik tidak ada di Phase 1 selain welcome.
- **Auth umum** — `/dashboard` (arahan peran: daftar org milik user + undangan pending), `/organizations/request` (form pengajuan; throttle anti-spam), `/invitations` (terima/tolak undangan milik email user).
- **`/organizer/{organization}`** — dashboard org, member list, undang member, ubah role/keluarkan, edit profil org. Route model binding **scoped**: `{organization}` di-resolve hanya dalam organisasi milik user; di luar scope → **404**. Policy: `OrganizationPolicy`, `MemberPolicy`, `InvitationPolicy`.
- **`/admin`** — antrean pengajuan (`pending` list + approve/reject), kelola organisasi (suspend/arsip/aktifkan), baca `audit_logs` + `security_logs` (read-only). Gate role global `super_admin`. Operasi sensitif (suspend, arsip) wajib **re-authentication + alasan** teraudit.

IDOR (SECURITY.md §4): resource tenant di luar scope → 404 (tidak membocorkan keberadaan); aksi yang resource-nya terlihat namun tidak diizinkan → 403. Konsisten per resource.

Transaksi wajib (`DB::transaction`): setujui pengajuan (buat org + membership owner + audit); terima undangan (buat membership + sinkron permission + audit); ubah role/keluarkan member (update + sinkron permission + audit); suspend/arsip org (update status + audit).

Semua input via Form Request; `$fillable` allowlist eksplisit; `organization_id`, `user_id`, `role`, status sensitif tidak pernah fillable. Tidak ada endpoint yang membiarkan user mengubah role/organisasi/permission milik sendiri (SECURITY.md §3).

## 4. Testing & Gates

Mengikuti TESTING.md, scope Phase 1:

- **Unit** — `OrganizationService` (approve → org+owner terbentuk; reject butuh alasan; suspend/arsip transition); `MembershipService` (invite → token; accept valid; role-change; kecualikan: ubah role sendiri ditolak, undang sebagai owner ditolak); policy unit (pemilik scope lolos, luar scope gagal).
- **Feature** — register/login/logout/reset/verifikasi (termasuk throttle 5/menit + lockout + respons generik); ajukan → setujui → org aktif + pemohon owner; ajukan → tolak + alasan; undang → terima → member; ubah role staff; keluarkan member; suspend org (butuh re-auth + alasan).
- **Authorization** — matriks per endpoint: guest → redirect login; staff tanpa permission → 403; staff beda org → 404; non-admin di `/admin` → 403/404.
- **Isolation** — Org A vs Org B simetris: dashboard, member list, undangan, profil org — 404/403 tanpa membocorkan keberadaan (TESTING.md §5).
- **Negatif** — mass-assignment (`role`, `organization_id` via request ditolak); privilege escalation; duplikat membership/undangan pending; undangan expired/ditolak; pengajuan ke-4 saat 3 pending (ditolak); seed super_admin idempotent.
- **Gates** — Pest hijau; Pint PASS; PHPStan/Larastan level 5 No errors; `composer audit` bersih critical/high.

## 5. Self-Review

1. **Placeholder scan:** seluruh tabel/kolom/status/permission bernama eksplisit; batas numerik konkret (password 12, throttle 5/menit, undangan +7 hari, 3 pengajuan pending). Tanpa TBD/TODO.
2. **Konsistensi internal:** "owner tidak dapat diundang" (§2) konsisten dengan "ownership hanya via pengajuan/transfer" (§2, §3); sinkronisasi permission terpusat di `MembershipService` (§1) konsisten dengan transaksi §3; append-only log (§2) konsisten dengan audit di setiap tulis (§3).
3. **Scope:** murni fondasi identitas/tenancy; event dan seterusnya eksplisit non-scope. Alur approval adalah jawaban user yang disetujui via Pendekatan 1 — bukan scope creep.
4. **Ambiguitas:** "transfer ownership eksplisit" disebut sebagai satu-satunya jalan owner-by-invite selain pengajuan — didefinisikan sebagai aksi owner → member staff lain dalam org yang sama, dengan re-auth + audit (bukan undangan). Batas undangan `+7 hari` dan `3 pengajuan pending` adalah nilai awal yang dapat dikonfigurasi kemudian.
