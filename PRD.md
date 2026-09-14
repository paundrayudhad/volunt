# PRD — Product Requirements Document

## 1. Vision

**WebVolunteer** adalah centralized event workforce platform: satu aplikasi
multi-tenant tempat banyak organizer mengelola banyak event, dan satu akun
volunteer dapat mengikuti banyak event.

Target event: music festival, concert, campus event, community event, sport
event, conference, creative festival, charity event, exhibition, public event.

## 2. Tujuan Produk

1. Organizer membuat dan mengelola banyak event dalam satu aplikasi.
2. Satu event memiliki banyak division, role, shift, volunteer.
3. Satu akun volunteer mengikuti banyak event.
4. Organizer mengelola recruitment → operasional → evaluasi end-to-end.
5. Platform reusable untuk event berikutnya tanpa project baru.
6. Data antar organizer terisolasi (security-critical).
7. Security dari level database hingga frontend.
8. Business-critical operation ber-transaction dan tervalidasi.
9. Aman terhadap concurrent registration.
10. Siap berkembang menjadi SaaS / technology platform.

## 3. User Types

| Role | Cakupan | Kemampuan inti |
|---|---|---|
| Super Admin | Global | Kelola user/organizer/event/permission/settings; suspend; audit & security log; analytics platform |
| Organizer Owner | 1 organisasi | Kelola organisasi + member; CRUD event; division/role/shift; registration; assignment; attendance; announcement; report |
| Organizer Staff | Organisasi + permission | Subset configurable (mis. `registration.review`, `attendance.read`) |
| Volunteer | Data milik sendiri | Profil, browse event, daftar, lihat status/assignment/schedule/attendance, terima announcement, unduh sertifikat |
| Guest | Publik | Lihat event publik, cari event, register, login |

Super Admin satu-satunya role lintas tenant.

## 4. Organizer / Tenant

Organizer adalah tenant utama. Profil: nama, slug, logo, deskripsi, kontak
(email/phone/website/social), branding, status (`active`, `suspended`, `archived`).

Keanggotaan via `organization_members` (`organization_id`, `user_id`, status,
`joined_at`) + role per-organisasi. Tidak ada global role yang membuka akses tenant.

## 5. Event

Fields: `organization_id`, nama, slug (unik per organisasi), deskripsi,
banner/thumbnail, kategori, venue, alamat, lat/long, timezone, `start_at`,
`end_at`, `registration_start_at`, `registration_end_at`, status, kapasitas,
kontak, terms, privacy notice. Branding per event (logo, banner, warna,
sponsor, social) hanya berlaku pada event tersebut.

### State transition event

```text
Draft → Published → Registration Open → Registration Closed →
Ongoing → Completed → Archived
                     ↘ Cancelled (dari banyak state, tercatat)
```

Aturan: transition divalidasi backend (`EventService::transitionTo()`),
status invalid via manipulasi request ditolak 422. `Cancelled` memerlukan
alasan; event `Cancelled`/`Archived` menolak registration baru dan
perubahan assignment.

## 6. Division, Role, Shift

- **Division**: `event_id`, nama, deskripsi, supervisor, status.
- **Role**: `event_id`, `division_id`, nama, deskripsi, quota, requirements,
  skill, lokasi, status. Counter `accepted_count` untuk kontrol quota atomik.
- **Shift**: `event_id`, `division_id`, `role_id`, `start_at`, `end_at`,
  lokasi, kapasitas, supervisor, status. Volunteer hanya melihat shift
  terkait assignment-nya.

## 7. Custom Registration Form

Organizer membuat field custom per event. Tipe: text, textarea, email, phone,
number, date, time, select, multi-select, radio, checkbox, URL, file.
Setiap field: label, tipe, required, placeholder, validation rule,
sort order, active. Validasi selalu backend; jawaban file mengikuti
aturan upload [SECURITY.md](SECURITY.md). Arbitrary HTML tidak disimpan
tanpa sanitasi allowlist.

## 8. Registration Flow & Status

```text
Browse → Detail → Pilih Role → Isi Form → Validasi → Submit → Pending →
Under Review → Accepted / Rejected / Waitlisted → Assignment → Schedule →
Attendance → Evaluasi → Certificate
```

Status: `Pending`, `Under Review`, `Accepted`, `Rejected`, `Waitlisted`,
`Cancelled`, `Withdrawn`. Transition valid:

| Dari | Ke |
|---|---|
| Pending | Under Review, Cancelled, Withdrawn |
| Under Review | Accepted, Rejected, Waitlisted, Cancelled, Withdrawn |
| Waitlisted | Accepted (slot tersedia), Rejected, Cancelled, Withdrawn |
| Accepted | Cancelled (oleh organizer, melepas quota) |
| Rejected/Cancelled/Withdrawn | terminal |

Volunteer hanya boleh: submit (→ Pending), withdraw milik sendiri.
Perubahan ke Accepted/Rejected/Waitlisted hanya oleh organizer ber-permission
via `RegistrationService`.

## 9. Business Rules Kritis

1. **Satu user = satu registration aktif per event**:
   unique constraint `registrations(user_id, event_id)` untuk status aktif
   (Pending/Under Review/Accepted/Waitlisted) + validasi backend.
2. **Quota tidak boleh terlampaui**: accept hanya via `QuotaService`
   (transaction + `SELECT ... FOR UPDATE` pada `event_roles`). Quota 20 →
   accepted maksimal 20. Kelebihan masuk waitlist.
3. **Assignment** menyimpan history status
   (Assigned → Reassigned → Confirmed → Completed); tidak ada hard delete
   tanpa alasan audit.
4. **Attendance**: check-in/out tervalidasi event + assignment + shift +
   time window + user dari auth context. QR = token signed, short-lived,
   anti-replay.
5. **Certificate**: diterbitkan setelah event selesai + attendance memenuhi
   syarat; ID unik + QR verifikasi publik dengan data minimal.
6. **Announcement**: target event/division/role/shift/individu; tercatat
   author, published_at, expiry.
7. **Notification**: async via queue (database → email; WhatsApp/push
   menyusul). Request HTTP tidak menunggu broadcast massal.
8. **Report/export**: selalu event-scoped, authorization checked, rate
   limited, audit logged; export besar via queue.

## 10. Modul Event Musik (opsional per event)

- **Artist liaison**: data artist (jadwal, venue, transport, requirements,
  liaison ter-assign) — restricted sesuai permission.
- **Incident**: kategori (medical, security, crowd, technical, lost & found,
  other), prioritas (low–critical), status (open → assigned → in progress →
  resolved → closed), scoped ke event.
- **Lost & found**: item, deskripsi, foto, lokasi, waktu, status, handler.
- **Talent pool**: pencarian volunteer berbasis skill/history dengan
  privacy rules (visibility, data minimization).

## 11. Scope per Phase

- **Phase 1 (Foundation)**: auth, user, organization, membership, RBAC,
  tenant isolation + test isolasi.
- **Phase 2 (Event)**: event, division, role, shift, public page.
- **Phase 3 (Volunteer)**: profile, registration, custom fields, seleksi,
  waitlist, quota + concurrency test.
- **Phase 4 (Operations)**: assignment, schedule, QR attendance,
  announcement, notification.
- **Phase 5 (Advanced)**: artist liaison, incident, lost & found,
  talent pool, certificate.
- **Phase 6 (Reporting)**: analytics, export, dashboard.
- **Phase 7 (Hardening)**: audit menyeluruh, hardening produksi.

## 12. Non-Goals (MVP)

Multi-bahasa UI, payment/subscription, mobile app native, WhatsApp/push
otomatis (cukup arsitektur siap), AI/matching otomatis, multi-tenancy
database-per-tenant.
