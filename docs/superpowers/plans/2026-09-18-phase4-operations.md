# Phase 4 (Operations) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Assignment organizer ke shift, schedule + QR volunteer, attendance scan, announcement terscope + notifikasi in-app.

**Architecture:** Modul domain `Operations` menempel rantai Phase 3 (`Registration accepted → Assignment → Attendance`); controller tipis → Form Request → Service → Model; `AssignmentService`/`AttendanceService` satu-satunya penulis status/counter; broadcast pengumuman via queue job.

**Tech Stack:** Laravel 13, PHP 8.4 (via `docker compose exec app`), PostgreSQL 16, Blade, Spatie permission, Pest, Pint, Larastan level 5.

**Spec:** `docs/superpowers/specs/2026-09-18-phase4-operations-design.md` (otoritas mengikat; plan ini argumennya; konflik diputus melawan spec).

## Global Constraints

- Prose/narasi/variabel tampilan Bahasa Indonesia; identifier kode (class, method, file, route name, branch) English (`$valid` dipertahankan); pesan user Bahasa Indonesia.
- Setiap step berisi konten nyata — tanpa TBD/TODO/placeholder.
- `git add` file eksplisit setelah `git status --porcelain`; NEVER `git add -A`.
- Commit berakhir `Co-Authored-By: Claude Code <noreply@anthropic.com>`.
- Kerja in place (tanpa worktree); semua PHP/composer/artisan via `docker compose exec app ...` (fallback `docker --context default compose exec app ...`).
- Controller tipis → Form Request → Service → Model; permission = kemampuan AND membership = cakupan (`can()` + `belongsToOrganization()`); owner dapat semua perm org via sync; audit/security log append-only.
- Check constraints via `DB::statement` (bukan `Blueprint::check`) — preseden Phase 2–3.
- `capacity` NULL pada shift = kuota tak terbatas (counter tetap naik, tanpa cek penuh) — keputusan desain ini.

---

### Task 1: Skema operations + models + permissions

**Files:**
- Create: `database/migrations/2026_09_18_000005_create_operations_tables.php`, `database/migrations/2026_09_18_000006_add_filled_count_to_event_shifts.php`, `database/migrations/2026_09_18_000007_create_notifications_table.php` (atau via `php artisan notifications:table` bila belum ada — cek dulu, jangan duplikat), `database/migrations/2026_09_18_000008_add_notification_preferences_to_users.php`, models `Assignment.php`, `AssignmentHistory.php`, `Attendance.php`, `AttendanceLog.php`, `QrToken.php`, `Announcement.php`, factories `AssignmentFactory`, `EventShiftFactory` (cek existing — extend bila ada)
- Modify: `MembershipService.php` (GRANULAR +6: `assignment.manage`, `assignment.read`, `attendance.record`, `attendance.read`, `announcement.publish`, `announcement.read`), `database/seeders/PermissionSeeder.php` (PERMISSIONS +6 sama), `Registration.php` (hasOne assignment), `EventShift.php` (hasMany assignments), `Event.php` (hasMany assignments/attendances/announcements), `User.php` (hasMany assignments/attendances/notifications morphpassthrough bila perlu)
- Test: `tests/Feature/OperationsModelRelationTest.php`

**Interfaces:**
- Consumes: Phase 2 `EventShift` (+`capacity` nullable), Phase 3 `Registration` + `TRANSITIONS`, `MembershipService::GRANULAR`.
- Produces: tabel + model + 6 permission untuk Task 2–6.

**Keputusan eksplisit:** `assignments.status` check (`assigned`,`reassigned`,`confirmed`,`completed`,`cancelled`); `attendances` unique `(assignment_id, shift_id)` + `idempotency_key` unique; `attendance_logs.action` check (`check_in`,`check_out`,`void`); `qr_tokens.token_hash` unique + `expires_at` + `used_at`/`revoked_at` nullable; `announcements.target_type` check 5 nilai; `notifications` ikut struktur bawaan Laravel (`id` uuid, `type`, `notifiable_type/id`, `data`, `read_at`); `users.notification_preferences` jsonb nullable. `TRANSITIONS` assignment sebagai const di model `Assignment` (cermin `Registration::TRANSITIONS`): `assigned → confirmed/completed/cancelled`, `confirmed → completed/cancelled`, `reassigned → confirmed/completed/cancelled`, terminal `completed/cancelled`.

- [ ] **Step 1: Tulis test relasi dulu (TDD, 4 test)**

```php
// tests/Feature/OperationsModelRelationTest.php
// helper prefix opsModel*
it('registration memiliki satu assignment', function () { /* Registration::factory()->create(); expect($reg->assignment)->toBeNull(); Assignment::factory()->create(['registration_id' => $reg->id]); expect($reg->refresh()->assignment)->not->toBeNull(); });
it('permission operations tersedia setelah seed', function () { /* $this->seed(PermissionSeeder::class); foreach (['assignment.manage','assignment.read','attendance.record','attendance.read','announcement.publish','announcement.read'] as $p) expect(Permission::findByName($p,'web'))->not->toBeNull(); });
it('assignment transitions const valid', function () { /* expect(Assignment::TRANSITIONS['assigned'])->toContain('confirmed'); terminal completed/cancelled => [] */ });
it('shift memiliki counter filled_count default 0', function () { /* EventShift::factory()->create(); expect($shift->filled_count)->toBe(0); */ });
```

- [ ] **Step 2: Run test, expect FAIL (tabel/model belum ada)**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OperationsModelRelationTest.php`
Expected: FAIL with "Class \"App\\Models\\Assignment\" not found" (atau tabel tak ada).

- [ ] **Step 3: Tulis migrasi + models + relasi + permissions**

Migrasi `000005` (assignments + assignment_histories + attendances + attendance_logs + qr_tokens + announcements; check via `DB::statement`; down drop reverse); `000006` (`filled_count` integer default 0 + check `>= 0` + check `filled_count <= capacity OR capacity IS NULL` — catatan: check lintas kolom nullable, tulis `CHECK (capacity IS NULL OR filled_count <= capacity)`); `000007` notifications (cek `php artisan notifications:table` existing — bila belum, buat manual sesuai struktur bawaan); `000008` (`notification_preferences` jsonb nullable). Models: fillable allowlist (tanpa `user_id/event_id/status/counter`), casts datetime, relasi dua arah. `MembershipService::GRANULAR` +6, `PermissionSeeder::PERMISSIONS` +6. Relasi di Registration/EventShift/Event/User.

- [ ] **Step 4: Run test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OperationsModelRelationTest.php`
Expected: PASS (4 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add database/migrations/2026_09_18_000005_create_operations_tables.php database/migrations/2026_09_18_000006_add_filled_count_to_event_shifts.php database/migrations/2026_09_18_000007_create_notifications_table.php database/migrations/2026_09_18_000008_add_notification_preferences_to_users.php app/Models/Assignment.php app/Models/AssignmentHistory.php app/Models/Attendance.php app/Models/AttendanceLog.php app/Models/QrToken.php app/Models/Announcement.php app/Services/MembershipService.php database/seeders/PermissionSeeder.php app/Models/Registration.php app/Models/EventShift.php app/Models/Event.php app/Models/User.php tests/Feature/OperationsModelRelationTest.php
git commit -m "feat: operations schema + models + permissions

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 2: AssignmentService (assign/reassign/confirm/cancel/bulk + kuota + overlap)

**Files:**
- Create: `app/Services/AssignmentService.php`, `app/Exceptions/ShiftFullException.php` (extends HttpException 422 'Kuota shift sudah penuh.', cermin `QuotaFullException`)
- Test: `tests/Feature/AssignmentServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models + `Assignment::TRANSITIONS` + `filled_count`; Phase 3 `Registration` (status accepted), `QuotaService` pola lock.
- Produces: `assign/reassign/confirm/cancel/bulkAssign` untuk Task 3.

**Keputusan eksplisit:** hanya registration `accepted` (422 bila bukan); shift harus satu event dengan registration (422); `capacity` NULL = skip cek penuh; konflik overlap = volunteer sama punya assignment aktif (`assigned/reassigned/confirmed`) di shift yang overlap waktu (`start_at < other.end_at && end_at > other.start_at`), 422 'Jadwal bentrok dengan shift lain.'; kuota via `EventShift::lockForUpdate` + increment `filled_count` (pola `QuotaService::accept`); `release` idempotent di nol; reassign = assignment sama pindah shift (status → `reassigned`, lepas lama + isi baru, history catat shift lama→baru); cancel assignment yang mengisi kuota → release; bulk maks 50, fail-closed 404 bila satu ID luar event, satu `DB::transaction` all-or-nothing; history + audit tiap transisi (`assignment.{to}`); `created_at => now()` eksplisit di histories (pelajaran Phase 3 Minor 4).

- [ ] **Step 1: Tulis test service dulu (TDD, 11 test, prefix `assignTes*`)**

Test: assign valid → assigned + filled_count 1 + history; shift penuh → `ShiftFullException`/422 + counter tetap; capacity null → assign tanpa batas; overlap → 422; registration pending → 422; shift beda event → 422; confirm → confirmed; reassign → reassigned + kuota lama 0 + baru 1 + history from/to + shift lama/baru; cancel → cancelled + release; bulk 3 sukses; bulk satu ID luar event → 404 + rollback penuh (counter 0).

- [ ] **Step 2: Run test, expect FAIL**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AssignmentServiceTest.php`
Expected: FAIL with "Class \"App\\Services\\AssignmentService\" not found".

- [ ] **Step 3: Implementasi service + exception**

`assign(registration, shift, actor, location?)`, `reassign(assignment, shiftBaru, actor)`, `confirm(assignment, actor)`, `cancel(assignment, actor, reason?)`, `bulkAssign(event, registrationIds, shift, actor)` — semua via private `terapkanStatus()` + `isiKuota()`/`lepasKuota()` (cermin `RegistrationService::terapkanStatus` + `QuotaService`).

- [ ] **Step 4: Run test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AssignmentServiceTest.php`
Expected: PASS (11 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Services/AssignmentService.php app/Exceptions/ShiftFullException.php tests/Feature/AssignmentServiceTest.php
git commit -m "feat: assignment service + shift quota + overlap guard

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 3: Assignment organizer (policy + controller + routes + views)

**Files:**
- Create: `app/Policies/AssignmentPolicy.php`, `app/Http/Requests/AssignRequest.php`, `app/Http/Requests/BulkAssignRequest.php`, `app/Http/Controllers/Organizer/AssignmentController.php`, views `resources/views/organizer/events/assignments/{index,show}.blade.php`, test `tests/Feature/OrganizerAssignmentTest.php`
- Modify: `routes/web.php` (nested `assignments` di bawah `{event}` setelah `registrations`), `app/Providers/AppServiceProvider.php` (binding `assignment` event-scoped → 404)

**Interfaces:**
- Consumes: Task 2 service; Phase 3 pola (`RegistrationPolicy`, `BulkReviewRequest` authorize via sample + fallback, binding event-scoped, `password.confirm` + throttle bulk).
- Produces: route `organizer.events.assignments.*` + binding `assignment`.

**Keputusan eksplisit:** policy `viewAny/view` = member + `assignment.read`; `manage` (assign/reassign/confirm/cancel/bulk) = member + `assignment.manage`; `BulkAssignRequest::authorize` ikut pola fixed Phase 3 (sample in-scope `can('manage', sample)` + fallback member+perm untuk event kosong); routes assign/bulk pakai `password.confirm` + `throttle:10,1`; `ShiftFullException` → `back()->withErrors` (pesan Indonesia); index paginate 15 + filter status/shift; flash Indonesia; `@can` di views.

- [ ] **Step 1: Tulis test dulu (TDD, 9 test, prefix `tugasTes*`)**

Test: index filter + paginasi; assign valid redirect + DB; assign shift penuh → error kuota; bulk 3 sukses; bulk satu ID luar event → 404 + rollback; staff read-only → 403 di assign; lintas event → 404; guest → login; reassign + confirm + cancel happy path.

- [ ] **Step 2: Run test, expect FAIL**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OrganizerAssignmentTest.php`
Expected: FAIL with "Route [organizer.events.assignments.index] not defined".

- [ ] **Step 3: Implementasi policy + requests + controller + binding + routes + views**

Tipis, pola `RegistrationController` Phase 3 (fail-closed 404 rethrow, withErrors untuk 422 service).

- [ ] **Step 4: Run test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OrganizerAssignmentTest.php`
Expected: PASS (9 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Policies/AssignmentPolicy.php app/Http/Requests/AssignRequest.php app/Http/Requests/BulkAssignRequest.php app/Http/Controllers/Organizer/AssignmentController.php routes/web.php app/Providers/AppServiceProvider.php resources/views/organizer/events/assignments/ tests/Feature/OrganizerAssignmentTest.php
git commit -m "feat: organizer assignment + bulk + scoped binding

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 4: Attendance + QR (service + scan organizer + QR volunteer)

**Files:**
- Create: `app/Services/AttendanceService.php`, `app/Http/Requests/ScanAttendanceRequest.php`, `app/Http/Requests/ManualAttendanceRequest.php`, `app/Http/Controllers/Organizer/AttendanceController.php`, `app/Http/Controllers/Volunteer/AttendanceController.php` (my QR: show + rotate), views `resources/views/registrations/qr.blade.php` + `resources/views/organizer/events/attendances/{index,scan}.blade.php`, tests `tests/Feature/AttendanceTest.php`
- Modify: `routes/web.php` (organizer `attendances/scan|manual` + volunteer `my/qr|qr/rotate`), `Assignment.php` (hasMany qrTokens/attendances), notch `AppServiceProvider` bila perlu binding attendance

**Interfaces:**
- Consumes: Task 1–2 (assignment aktif, shift window); Phase 3 pola volunteer own-only + 404.
- Produces: endpoint scan + QR volunteer untuk Task 6 (matriks).

**Keputusan eksplisit:** token acak 32 byte hex, simpan sha256, mentah tampil sekali; expiry 5 menit; sekali pakai per aksi (`used_at`); rotate mencabut lama (`revoked_at`); jendela `start_at − 30 mnt` s/d `end_at` (422 di luar); `late` bila check-in > `start_at + 15 mnt`; check-out wajib setelah check-in; token berbeda per aksi; replay → 422; token asing → 404; manual butuh alasan (`method=manual`); idempotency key per aksi (replay key sama → existing); scan throttle `30,1`; log append-only (ip + user agent) + audit; QR render sebagai teks token + instruksi (tanpa library QR image — cukup string yang dipindai aplikasi scanner; bila perlu gambar, pakai API-agnostic SVG inline sederhana — putuskan inline di implementasi, catat di report).

- [ ] **Step 1: Tulis test dulu (TDD, 12 test, prefix `hadirTes*`)**

Test: check-in valid → present + log; check-in telat → late; luar jendela (terlalu awal/lewat end) → 422; token expired → 422; token bekas pakai → 422; replay idempotency → existing; token assignment lain → 404; check-out tanpa check-in → 422; check-out valid → checked_out_at terisi; manual tanpa alasan → 422; manual valid → method manual; rotate mencabut lama (lama → 422).

- [ ] **Step 2: Run test, expect FAIL**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AttendanceTest.php`
Expected: FAIL with "Class \"App\\Services\\AttendanceService\" not found".

- [ ] **Step 3: Implementasi service + controllers + views + routes**

`AttendanceService::issueToken(assignment, actor)` (buat + cabut lama), `::checkIn(token, actor, idempotencyKey)`, `::checkOut(...)`, `::manual(assignment, shift, actor, alasan, ...)` — satu transaction masing-masing.

- [ ] **Step 4: Run test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AttendanceTest.php`
Expected: PASS (12 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Services/AttendanceService.php app/Http/Requests/ScanAttendanceRequest.php app/Http/Requests/ManualAttendanceRequest.php app/Http/Controllers/Organizer/AttendanceController.php app/Http/Controllers/Volunteer/AttendanceController.php routes/web.php resources/views/registrations/qr.blade.php resources/views/organizer/events/attendances/ tests/Feature/AttendanceTest.php
git commit -m "feat: QR attendance + scan + manual fallback

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 5: Announcement + notifikasi in-app (service + job + controller + bell)

**Files:**
- Create: `app/Services/AnnouncementService.php`, `app/Jobs/BroadcastAnnouncement.php`, `app/Notifications/EventAnnouncement.php` (database channel), `app/Policies/AnnouncementPolicy.php`, `app/Http/Requests/PublishAnnouncementRequest.php`, `app/Http/Controllers/Organizer/AnnouncementController.php`, `app/Http/Controllers/Volunteer/AnnouncementController.php` (index milik saya), `app/Http/Controllers/Volunteer/NotificationController.php` (index + read), views organizer `announcements/{index,create,show}` + volunteer `announcements/index` + bell unread-count di layout existing (ikuti struktur layout yang ada — cek `resources/views/layouts/`; catat path yang dipakai di report), test `tests/Feature/AnnouncementTest.php`
- Modify: `routes/web.php` (organizer nested + volunteer `announcements` + `notifications`), `AppServiceProvider` (binding `announcement` event-scoped)

**Interfaces:**
- Consumes: Task 1 models; assignment (resolve penerima per target); pola policy/binding Task 3.
- Produces: pengumuman + notifikasi untuk Task 6.

**Keputusan eksplisit:** policy `publish` = member + `announcement.publish`; `view` = member + `announcement.read`; draft (`published_at` null) 404/hidden untuk volunteer; terbit → `BroadcastAnnouncement::dispatch` (queue database; di test `Queue::fake` + assert dispatched, plus satu test dispatch-sync menulis notifikasi); resolve penerima: `event` = semua user dengan registration accepted di event; `division/role/shift` = filter via assignments; `individual` = user target (wajib satu event — validasi `target_id` user punya registration di event); expired (`expires_at` lewat) tak tampil; notifikasi `read` own-only; bell = count unread di layout (partial, tanpa JS framework); throttle terbit 10/menit.

- [ ] **Step 1: Tulis test dulu (TDD, 10 test, prefix `umumTes*`)**

Test: draft tak terlihat volunteer; terbit event → semua accepted dapat notifikasi; target role → hanya role itu; target shift → hanya shift itu; target individu → satu user; non-target assertDontSee; expired tak tampil; staff tanpa perm → 403; guest → login; read milik sendiri + milik orang lain 404.

- [ ] **Step 2: Run test, expect FAIL**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AnnouncementTest.php`
Expected: FAIL with "Route [organizer.events.announcements.index] not defined".

- [ ] **Step 3: Implementasi service + job + notification + policy + controllers + views + routes**

`AnnouncementService::publish(announcement, actor)` (set published_at + dispatch job); job resolve + `Notification::send` (database). Satu transaction untuk publish + audit.

- [ ] **Step 4: Run test sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/AnnouncementTest.php`
Expected: PASS (10 test).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Services/AnnouncementService.php app/Jobs/BroadcastAnnouncement.php app/Notifications/EventAnnouncement.php app/Policies/AnnouncementPolicy.php app/Http/Requests/PublishAnnouncementRequest.php app/Http/Controllers/Organizer/AnnouncementController.php app/Http/Controllers/Volunteer/AnnouncementController.php app/Http/Controllers/Volunteer/NotificationController.php routes/web.php app/Providers/AppServiceProvider.php resources/views/organizer/events/announcements/ resources/views/announcements/ tests/Feature/AnnouncementTest.php
git commit -m "feat: announcements + in-app notifications broadcast

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

### Task 6: Schedule volunteer + matriks otorisasi + isolasi + konkurensi + gates

**Files:**
- Create: `app/Http/Controllers/Volunteer/ScheduleController.php` (`GET /my/schedule`), view `resources/views/registrations/schedule.blade.php`, tests `tests/Feature/OperationsAuthorizationTest.php`, `tests/Feature/OperationsIsolationTest.php`, `tests/Feature/ShiftQuotaConcurrencyTest.php`
- Modify: `routes/web.php` (`my/schedule`), `README.md` (swap Phase 3 → Phase 4 byte-exact ala Phase 2–3)
- Test: file di atas

**Interfaces:**
- Consumes: Task 2–5 routes; pola `RegistrationAuthorizationTest/IsolationTest` + `QuotaConcurrencyTest` Phase 3 (fork/pcntl + skip by design + sequential residual).
- Produces: gates hijau Phase 4.

**Keputusan eksplisit:** schedule = daftar assignments milik sendiri per event (shift + lokasi + status + status attendance); matriks >20 asersi guest-first (staff read-only 403 semua mutasi: assign/bulk/scan/manual/publish; lintas org/event 404; volunteer own-only; guest login); isolasi A↔B simetris + katalog assertDontSee; konkurensi rebutan slot terakhir shift (fork + sequential residual + `filled_count <= capacity` invariant); admin read-only GET ikut matriks bila Task 3/5 menambah route admin (bila tidak ada route admin operations, catat non-scope di report — jangan buat route dadakan).

- [ ] **Step 1: Tulis schedule controller + 3 file test (TDD)**

Schedule: milik sendiri tampil (shift/lokasi/status), milik orang lain 404, guest login. Matriks otorisasi, isolasi, konkurensi ikut pola Phase 3.

- [ ] **Step 2: Run test, expect FAIL**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OperationsAuthorizationTest.php`
Expected: FAIL (route/controller belum ada — catat pesan aktual di report).

- [ ] **Step 3: Implementasi schedule + perbaiki gap yang ditemukan test**

Hanya schedule + view + route di task ini; gap lain → catat sebagai temuan untuk fix wave (jangan melebar).

- [ ] **Step 4: Run 3 file + suite penuh sampai hijau**

Run: `docker compose exec app ./vendor/bin/pest tests/Feature/OperationsAuthorizationTest.php tests/Feature/OperationsIsolationTest.php tests/Feature/ShiftQuotaConcurrencyTest.php`
Expected: PASS (skip pcntl by design bila tak tersedia).
Lalu: `docker compose exec app ./vendor/bin/pest` (suite penuh), `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --level=5`, `composer audit` (catat bila timeout jaringan — preseden Phase 3, bukan blocker).

- [ ] **Step 5: Commit**

```bash
git status --porcelain
git add app/Http/Controllers/Volunteer/ScheduleController.php resources/views/registrations/schedule.blade.php routes/web.php README.md tests/Feature/OperationsAuthorizationTest.php tests/Feature/OperationsIsolationTest.php tests/Feature/ShiftQuotaConcurrencyTest.php
git commit -m "feat: volunteer schedule + operations gates

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---
