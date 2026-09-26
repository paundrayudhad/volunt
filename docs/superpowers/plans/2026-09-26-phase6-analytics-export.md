# Phase 6 Analytics, Reporting, Export & Backlog Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul Analytics & Reporting (Event & Organization level), Data Export Engine (CSV & XLSX via PhpSpreadsheet), penyelesaian filter sertifikat organizer & global admin view, serta pagination verifikasi sertifikat.

**Architecture:** Modul analitik agregat real-time via `EventAnalyticsService`, `OrganizationAnalyticsService`, dan `AdminAnalyticsService` dengan single-query PostgreSQL aggregates; `DataExportService` dengan streamed response $O(1)$ memory untuk CSV & XLSX; `phpoffice/phpspreadsheet` untuk pembentukan spreadsheet; Form Requests & Policy untuk kontrol otorisasi RBAC Spatie; Controller tipis; Blade views Tailwind; serta Test suite komprehensif.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, PhpOffice/PhpSpreadsheet (^3.0 | ^4.0), Spatie Permission, Pest, Blade, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-09-26-phase6-analytics-export-design.md`

## Global Constraints

- Docker via `docker compose exec app` untuk semua perintah artisan/composer/pest.
- Full suite via biner langsung dengan memory 1G: `php -d memory_limit=1G ./vendor/bin/pest`.
- Quality gates per task: Pest hijau, Pint (`./vendor/bin/pint --test`), Larastan level 5 (`./vendor/bin/phpstan analyse --no-progress`), `composer audit` bersih di akhir.
- Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model; business logic hanya di service.
- Ekspor CSV stream wajib menggunakan `LazyCollection` (`cursor()`) untuk memori konstan $O(1)$.
- Rate limiting pada rute ekspor: `throttle:10,1`.
- Flash pesan berbahasa Indonesia.

---

## File Map

**Buat Baru:**
- `app/Services/EventAnalyticsService.php` — Agregasi metrik performa event (funnel, presensi, utilisasi peran/shift, insiden).
- `app/Services/OrganizationAnalyticsService.php` — Agregasi portofolio seluruh event per organisasi.
- `app/Services/AdminAnalyticsService.php` — Agregasi tingkat platform untuk Superadmin.
- `app/Services/DataExportService.php` — Mesin ekspor CSV streamed dan XLSX untuk datasets registrations, attendances, incidents, certificates.
- `app/Policies/AnalyticsPolicy.php` — Kebijakan otorisasi untuk view analitik dan ekspor data.
- `app/Http/Controllers/Organizer/EventAnalyticsController.php` — Controller dashboard analitik event.
- `app/Http/Controllers/Organizer/OrganizationAnalyticsController.php` — Controller portfolio organisasi.
- `app/Http/Controllers/Organizer/EventExportController.php` — Controller endpoint download ekspor dataset.
- `app/Http/Controllers/Admin/AnalyticsController.php` — Controller dashboard analitik Superadmin.
- `app/Http/Controllers/Admin/CertificateController.php` — Controller daftar sertifikat global Superadmin.
- `database/migrations/2026_09_26_000001_backfill_analytics_permissions.php` — Backfill permission `analytics.view` & `analytics.export`.
- Blade Views:
  - `resources/views/organizer/events/analytics/index.blade.php`
  - `resources/views/organizer/analytics/index.blade.php`
  - `resources/views/admin/analytics/index.blade.php`
  - `resources/views/admin/certificates/index.blade.php`
- Tests:
  - `tests/Feature/EventAnalyticsTest.php`
  - `tests/Feature/OrganizationAnalyticsTest.php`
  - `tests/Feature/EventExportTest.php`
  - `tests/Feature/AdminCertificateTest.php`
  - `tests/Feature/CertificateBacklogTest.php`

**Ubah:**
- `composer.json` — Tambahkan package `phpoffice/phpspreadsheet`.
- `database/seeders/PermissionSeeder.php` — Tambahkan `analytics.view` & `analytics.export`.
- `app/Services/MembershipService.php` — Sinkronkan permission baru ke `owner`.
- `app/Http/Controllers/Organizer/CertificateController.php` — Tambah filter status (`valid`/`revoked`) & paginasi verifikasi.
- `resources/views/organizer/certificates/index.blade.php` — Tambahkan filter dropdown status.
- `resources/views/organizer/certificates/show.blade.php` — Tampilkan paginasi verifikasi.
- `routes/web.php` — Daftarkan rute analitik, ekspor, dan admin certificate.

---

### Task 1: Package Dependensi, Permission & Backfill

**Files:**
- Modify: `composer.json`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `app/Services/MembershipService.php`
- Create: `database/migrations/2026_09_26_000001_backfill_analytics_permissions.php`

- [ ] **Step 1: Install `phpoffice/phpspreadsheet` via Docker**
Jalankan `docker compose exec app composer require phpoffice/phpspreadsheet`

- [ ] **Step 2: Tambahkan permission ke PermissionSeeder & MembershipService**
Tambah `analytics.view` dan `analytics.export` ke list permission dan konstanta `MembershipService::GRANULAR`.

- [ ] **Step 3: Tulis migrasi backfill idempoten**
Buat migrasi `2026_09_26_000001_backfill_analytics_permissions.php` untuk memberikan permission baru ke semua active owner.

- [ ] **Step 4: Jalankan migrasi & verifikasi**
Jalankan `docker compose exec app php artisan migrate`

---

### Task 2: Service Layer Analitik (Event, Organization, Admin)

**Files:**
- Create: `app/Services/EventAnalyticsService.php`
- Create: `app/Services/OrganizationAnalyticsService.php`
- Create: `app/Services/AdminAnalyticsService.php`
- Test: `tests/Feature/EventAnalyticsTest.php`
- Test: `tests/Feature/OrganizationAnalyticsTest.php`

- [ ] **Step 1: Tulis feature test untuk perhitungan metrik analitik event & org**
Tes perhitungan funnel konversi, attendance rate, utilisasi role/shift, serta isolasi tenant.

- [ ] **Step 2: Implementasikan `EventAnalyticsService`**
Kalkulasi metrik menggunakan query PostgreSQL aggregate yang efisien (`COUNT(*) FILTER (...)`).

- [ ] **Step 3: Implementasikan `OrganizationAnalyticsService` & `AdminAnalyticsService`**
Agregasi portofolio multi-event dan metrik platform.

- [ ] **Step 4: Jalankan test dan pastikan PASS**
`docker compose exec app ./vendor/bin/pest tests/Feature/EventAnalyticsTest.php tests/Feature/OrganizationAnalyticsTest.php`

---

### Task 3: Data Export Engine (CSV Stream & XLSX)

**Files:**
- Create: `app/Services/DataExportService.php`
- Test: `tests/Feature/EventExportTest.php`

- [ ] **Step 1: Tulis test untuk ekspor data CSV & XLSX**
Verifikasi streaming CSV, file XLSX, validasi kolom, isolasi tenant, dan pencatatan audit log `event.exported`.

- [ ] **Step 2: Implementasikan `DataExportService`**
Mendukung 4 dataset: `registrations`, `attendances`, `incidents`, `certificates` dengan streaming CSV $O(1)$ RAM dan PhpSpreadsheet XLSX generator.

- [ ] **Step 3: Jalankan test dan pastikan PASS**
`docker compose exec app ./vendor/bin/pest tests/Feature/EventExportTest.php`

---

### Task 4: Controllers, Requests, Policies & Routes

**Files:**
- Create: `app/Policies/AnalyticsPolicy.php`
- Create: `app/Http/Controllers/Organizer/EventAnalyticsController.php`
- Create: `app/Http/Controllers/Organizer/OrganizationAnalyticsController.php`
- Create: `app/Http/Controllers/Organizer/EventExportController.php`
- Create: `app/Http/Controllers/Admin/AnalyticsController.php`
- Create: `app/Http/Controllers/Admin/CertificateController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Buat Policy & Otorisasi**
Daftarkan `AnalyticsPolicy` untuk memeriksa keanggotaan org dan permission `analytics.view`/`analytics.export`.

- [ ] **Step 2: Buat Controller tipis untuk analitik, ekspor, dan admin certificates**
Integrasikan service ke controller responses.

- [ ] **Step 3: Daftarkan rute di `routes/web.php`**
Tambah rute organizer analitik, ekspor (`throttle:10,1`), serta rute admin `/admin/analytics` dan `/admin/certificates`.

---

### Task 5: Blade Views & Penyelesaian Backlog Sertifikat

**Files:**
- Create: `resources/views/organizer/events/analytics/index.blade.php`
- Create: `resources/views/organizer/analytics/index.blade.php`
- Create: `resources/views/admin/analytics/index.blade.php`
- Create: `resources/views/admin/certificates/index.blade.php`
- Modify: `app/Http/Controllers/Organizer/CertificateController.php`
- Modify: `resources/views/organizer/certificates/index.blade.php`
- Modify: `resources/views/organizer/certificates/show.blade.php`
- Test: `tests/Feature/AdminCertificateTest.php`
- Test: `tests/Feature/CertificateBacklogTest.php`

- [ ] **Step 1: Tulis test untuk filter sertifikat organizer & admin certificates**
Verifikasi filter `valid`/`revoked` dan pagination verifikasi.

- [ ] **Step 2: Update `CertificateController` & Blade sertifikat**
Tambahkan filter status dan paginasi `certificate_verifications`.

- [ ] **Step 3: Buat Blade views analitik dan ekspor**
Dashboard KPI tiles, Funnel bars, tables, dan tombol download CSV/XLSX.

- [ ] **Step 4: Jalankan seluruh test suite Phase 6**
`docker compose exec app ./vendor/bin/pest tests/Feature/EventAnalyticsTest.php tests/Feature/OrganizationAnalyticsTest.php tests/Feature/EventExportTest.php tests/Feature/AdminCertificateTest.php tests/Feature/CertificateBacklogTest.php`

---

### Task 6: Full Test Suite, Pint & Larastan Quality Gates

- [ ] **Step 1: Jalankan Laravel Pint**
`docker compose exec app ./vendor/bin/pint --test`

- [ ] **Step 2: Jalankan Larastan Level 5**
`docker compose exec app ./vendor/bin/phpstan analyse --no-progress`

- [ ] **Step 3: Jalankan Full Pest Test Suite**
`docker compose exec app php -d memory_limit=1G ./vendor/bin/pest`
