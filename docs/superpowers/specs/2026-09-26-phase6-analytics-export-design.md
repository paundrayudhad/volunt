# Phase 6 (Analytics, Reporting, Export & Backlog Polish) — Design Spec

**Goal:** Membangun modul **Analytics & Reporting** untuk menyajikan metrik performa event dan portofolio organisasi secara real-time, mesin **Data Export (CSV & XLSX)** dengan arsitektur efisien memori (*streamed response* & *chunking*), serta menyelesaikan *technical debt* dan *backlog* kredensial sertifikat.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16, PhpOffice/PhpSpreadsheet, Spatie Permission, Pest, Blade, Tailwind CSS.

---

## 1. Core Architecture & Analytics Metrics

### 1.1 Service Layer Arsitektur
Arsitektur analitik menggunakan pendekatan **Live Query Aggregation** yang dioptimasi untuk efisiensi tinggi pada volume data besar (menghindari N+1 query melalui agregasi SQL murni PostgreSQL dan *chunked lazy streaming*).

| Service | Tanggung Jawab |
|---|---|
| `App\Services\EventAnalyticsService` | Menghitung metrik analitik spesifik per event: conversion funnel registrasi, utilisasi kuota peran & shift, performa presensi, serta ringkasan insiden & artist liaison. |
| `App\Services\OrganizationAnalyticsService` | Agregasi metrik portofolio lintas event milik organisasi (total relawan unik, rasio penyelesaian event, jam kontribusi relawan). |
| `App\Services\AdminAnalyticsService` | Agregasi tingkat platform: status organisasi, total event publik, pertumbuhan akun pengguna, dan ringkasan audit log. |
| `App\Services\DataExportService` | Menghasilkan berkas ekspor tabular (CSV Streamed & XLSX Workbook) untuk berbagai dataset dengan isolasi tenant dan pemakaian memori konstan $O(1)$. |

---

### 1.2 Struktur Metrik Analitik Event (`EventAnalyticsService`)

```php
namespace App\Services;

use App\Models\Event;

class EventAnalyticsService
{
    /**
     * @return array{
     *     registration_funnel: array{total: int, pending: int, under_review: int, accepted: int, rejected: int, waitlisted: int, cancelled: int, withdrawn: int},
     *     attendance_summary: array{total_assignments: int, present: int, late: int, absent: int, attendance_rate_pct: float},
     *     role_utilization: array<int, array{role_name: string, quota: int, accepted_count: int, utilization_pct: float}>,
     *     shift_utilization: array<int, array{shift_name: string, capacity: int, filled_count: int, utilization_pct: float}>,
     *     incident_summary: array{total: int, open: int, investigating: int, resolved: int, critical_count: int},
     *     certificate_summary: array{issued: int, revoked: int, eligible_candidates: int}
     * }
     */
    public function getEventSummary(Event $event): array;
}
```

#### Optimasi Agregasi SQL (High-Volume Resilience)
Menghitung seluruh metrik registrasi dan presensi dalam single aggregate query untuk mengeliminasi overhead runtime:
```sql
SELECT 
    COUNT(*) AS total_registrations,
    COUNT(*) FILTER (WHERE status = 'pending') AS pending_count,
    COUNT(*) FILTER (WHERE status = 'under_review') AS under_review_count,
    COUNT(*) FILTER (WHERE status = 'accepted') AS accepted_count,
    COUNT(*) FILTER (WHERE status = 'rejected') AS rejected_count,
    COUNT(*) FILTER (WHERE status = 'waitlisted') AS waitlisted_count
FROM registrations 
WHERE event_id = ?;
```

---

## 2. Data Export Engine (CSV & XLSX)

### 2.1 Format & Dataset yang Didukung
`DataExportService` menyediakan 4 jenis dataset per event:
1. **`registrations`**: ID Pendaftaran, Nama Relawan, Email, Role, Status Pendaftaran, Waktu Daftar, Jawaban Custom Form (diratakan menjadi kolom dinamis).
2. **`attendances`**: Nama Relawan, Role, Divisi, Nama Shift, Waktu Mulai & Selesai Shift, Waktu Check-in, Waktu Check-out, Status Kehadiran (`present`, `late`, `absent`), Metode (`qr`/`manual`).
3. **`incidents`**: ID Insiden, Judul, Kategori, Tingkat Keparahan (`low`, `medium`, `high`, `critical`), Status (`open`, `investigating`, `resolved`), Pelapor, Petugas Penanggung Jawab, Waktu Lapor.
4. **`certificates`**: Nomor Sertifikat, Nama Relawan, Status Keabsahan (`valid`/`revoked`), Tanggal Terbit, Tanggal Cabut, Alasan Pencabutan.

### 2.2 Mekanisme Ekspor & Streaming
- **CSV (Native Streamed Response)**:
  - Menggunakan `Symfony\Component\HttpFoundation\StreamedResponse` langsung menulis ke *output stream* (`php://output`) dengan `fputcsv()`.
  - Menggunakan `cursor()` (Laravel `LazyCollection`) sehingga memori RAM konstan $O(1)$ berapapun jumlah baris.
- **XLSX (PhpSpreadsheet)**:
  - Menggunakan `PhpOffice\PhpSpreadsheet\Spreadsheet` dan `PhpOffice\PhpSpreadsheet\Writer\Xlsx`.
  - Menggunakan chunked processing untuk meminimalkan alokasi heap PHP.

### 2.3 Keamanan, Otorisasi & Audit
- Permission baru: `analytics.view` dan `analytics.export` (disinkronkan ke role `owner` via `MembershipService`).
- Endpoint ekspor mewajibkan otorisasi keanggotaan organisasi dan permission `analytics.export`.
- Setiap aktivitas ekspor data dicatat ke `AuditLogService` (`event.exported`, detail format `csv`/`xlsx` dan dataset).
- Rate Limiting: `throttle:10,1` untuk mencegah DoS pada request pembentukan spreadsheet.

---

## 3. Rute & Otorisasi

### 3.1 Rute Organizer
```php
Route::prefix('organizer/{organization}')->name('organizer.')->group(function () {
    // Org-Level Portfolio Analytics
    Route::get('analytics', [OrganizerAnalyticsController::class, 'index'])
        ->name('analytics.index');

    Route::prefix('events/{event}')->group(function () {
        // Event-Level Analytics Dashboard
        Route::get('analytics', [OrganizerEventAnalyticsController::class, 'index'])
            ->name('events.analytics.index');

        // Data Export Hub
        Route::get('export/{dataset}', [OrganizerEventExportController::class, 'export'])
            ->middleware('throttle:10,1')
            ->name('events.export');
    });
});
```

### 3.2 Rute Admin Superadmin
```php
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    // Admin Platform Analytics Dashboard
    Route::get('analytics', [AdminAnalyticsController::class, 'index'])
        ->name('analytics.index');

    // Admin Global Read-Only Certificates List
    Route::get('certificates', [AdminCertificateController::class, 'index'])
        ->name('certificates.index');
});
```

---

## 4. Antarmuka Pengguna (Blade Views) & Polish Backlog

### 4.1 Tampilan Baru
1. **Event Analytics Dashboard (`resources/views/organizer/events/analytics/index.blade.php`)**:
   - Kartu KPI: Total Pendaftar, Acceptance Rate, Attendance Rate, Total Jam Terpenuhi, Insiden Terbuka.
   - Conversion Funnel Bar (CSS murni / Tailwind).
   - Tabel utilisasi peran dan shift.
   - Action Hub Ekspor CSV/XLSX.
2. **Organization Portfolio Analytics (`resources/views/organizer/analytics/index.blade.php`)**:
   - Metrik komparatif seluruh event yang diselenggarakan oleh organisasi.
3. **Admin Certificates Index (`resources/views/admin/certificates/index.blade.php`)**:
   - Tampilan read-only daftar seluruh sertifikat lintas organisasi dengan filter status (`valid`/`revoked`) dan pencarian nomor sertifikat.

### 4.2 Penyelesaian Backlog & Technical Debt
1. **Filter Index Sertifikat Organizer (`organizer/events/certificates/index.blade.php`)**:
   - Menambahkan filter status: `all`, `valid`, `revoked`.
2. **Paginasi Riwayat Verifikasi Sertifikat**:
   - Membatasi query `certificate_verifications` pada halaman detail sertifikat organizer menggunakan `paginate(15)` alih-alih `get()`.

---

## 5. Rencana Pengujian (Testing Strategy)

1. **`EventAnalyticsTest.php`**:
   - Memastikan keakuratan kalkulasi funnel pendaftaran dan rasio kehadiran.
   - Memverifikasi isolasi tenant antar event organisasi berbeda.
2. **`EventExportTest.php`**:
   - Memvalidasi respons header dan struktur baris berkas CSV.
   - Memvalidasi respons berkas XLSX.
   - Memastikan otorisasi 403 untuk user tanpa permission `analytics.export`.
   - Memverifikasi pencatatan audit log `event.exported`.
3. **`AdminCertificateTest.php` & `CertificateBacklogTest.php`**:
   - Memastikan filter `valid` dan `revoked` bekerja pada daftar sertifikat organizer.
   - Memastikan rute `/admin/certificates` hanya dapat diakses oleh `super_admin`.
   - Memverifikasi paginasi riwayat verifikasi sertifikat.

---

## 6. Quality Gates
- **Pest Test Suite**: 100% PASS (seluruh test suite Phase 1 s/d Phase 6).
- **Laravel Pint**: 100% PASS bebas pelanggaran gaya kode.
- **Larastan**: Level 5 PASS (0 errors).
