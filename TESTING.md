# TESTING

Framework: **Pest** (di atas PHPUnit) + database PostgreSQL khusus test
(`*_test`, migration fresh per suite). Target: setiap perubahan penting
memiliki test; setiap bug diperbaiki pada root cause + regression test.

## 1. Piramida Test

```text
Unit (service/domain)  — cepat, banyak
Feature/Integration    — alur HTTP + Livewire per role
Authorization          — matriks SECURITY.md §5 per endpoint
Tenant Isolation       — Org A vs Org B, security-critical
Concurrency            — race quota/assignment/attendance
E2E (selektif)         — 3 alur utama (volunteer, organizer, admin)
```

Kualitas: Pint (PSR-12), PHPStan/Larastan level ≥5, `composer audit`
bersih dari critical/high tanpa alasan terdokumentasi, review N+1
(`assertNoNPlusOne` / query-count assertion pada list berat).

## 2. Unit Test (contoh)

- `QuotaService`: accept saat slot tersedia → `accepted_count++`;
  accept saat penuh → exception + waitlist; release → counter turun,
  tidak pernah negatif (check constraint).
- `RegistrationService`: transition valid lolos, invalid ditolak;
  volunteer tidak bisa set Accepted; duplikat aktif ditolak.
- `EventService`: transition status valid/invalid; cancel butuh alasan.
- `AttendanceService`: di luar time window ditolak; QR expired/nonce
  dipakai ditolak; idempotency key sama → record existing.
- `CertificateService`: eligibility (event completed + attendance
  memenuhi syarat) benar; nomor unik.
- Policy unit: tiap policy lolos untuk pemilik scope, gagal di luar scope.

## 3. Feature / Integration Test

Auth (register, login, logout, verifikasi email, reset password);
organisasi (create, invite member, suspend); event (CRUD + publish flow);
division/role/shift; custom fields; registration submit → review →
accept/reject/waitlist → assignment → schedule → check-in/out →
certificate; announcement + notifikasi terkirim via queue (fake);
export scoped + teraudit.

Setiap alur diuji untuk 5 aktor: Super Admin, Owner, Staff (dengan dan
tanpa permission relevan), Volunteer (own-only), Guest (ditolak/dibatasi).

## 4. Authorization Test

Untuk tiap endpoint: 200/302 bagi berhak, 403/404 bagi tidak berhak,
302→login bagi guest. Fokus: Staff **tanpa** permission ditolak meski
satu organisasi; Staff **dengan** permission ditolak di luar organisasinya;
Volunteer ditolak atas data volunteer lain (IDOR langsung).

## 5. Tenant Isolation Test (wajib, security-critical)

Skenario baku:

```text
Org A: Event A, Registration A, Assignment A, Attendance A, Incident A
Org B: Event B, Registration B, Assignment B, Attendance B, Incident B
```

Asert: A tidak bisa read/update/delete/export B pada **seluruh**
resource (events, divisions, roles, shifts, registrations, assignments,
attendance, incidents, certificates, exports, announcements) — 404/403
tanpa membocorkan keberadaan. Berlaku simetris (B vs A).

## 6. Concurrency Test

- **Quota**: role tersisa 1 slot; 3 user submit+accept bersamaan
  (proses/paralel test runner, mis. Pest parallel + worker DB terpisah)
  → tepat 1 Accepted, 2 Waitlisted/Rejected;
  `accepted_count ≤ quota` selalu.
- Duplikat: double-submit (idempotency key sama) → 1 record;
  check-in ganda → 1 attendance; accept ganda → counter naik 1x.
- Cancellation bersamaan dengan accept → state konsisten (salah satu
  menang, tidak ada counter negatif).
- Syarat: test berjalan melawan PostgreSQL (bukan SQLite) agar
  `FOR UPDATE` dan constraint parsial benar-benar teruji.

## 7. Edge Cases

Registrasi saat: event draft/cancelled/archived, periode tutup, role
nonaktif, quota penuh, user sudah terdaftar, user suspended, organizer
suspended. QR expired/duplikat. File: invalid/oversize/malicious.
Session expired. ID organisasi/event invalid. Export tanpa permission.
Payload besar (request size limit) dan spam (throttle).

## 8. E2E (selektif, 3 alur)

1. **Volunteer**: register → verifikasi → login → browse → pilih role →
   submit → cek status → terima → lihat schedule → check-in/out →
   terima sertifikat → verifikasi publik.
2. **Organizer**: login → buat event → division/role/shift → buka
   registrasi → review → accept → assign → monitor attendance →
   tutup event → generate report.
3. **Admin**: login → kelola organizer/user/permission → review log →
   suspend → audit tercatat.

## 9. Definition of Done (testing)

Unit + feature + authorization + isolation + concurrency hijau;
edge cases tercakup; static analysis & audit bersih; tidak ada N+1 pada
list utama (registrations, volunteers, attendance, reports);
manual checklist SECURITY.md §14 lolos sebelum rilis.
