# WebVolunteer — Multi-Event Volunteer & Event Workforce Management Platform

Platform web **multi-organizer / multi-event** untuk mengelola recruitment, seleksi,
penempatan, scheduling, attendance, komunikasi, dan evaluasi volunteer/crew.

Satu aplikasi melayani banyak organizer dan banyak event secara bersamaan:

```text
Platform
│
├── Organizer A ── Event A1, A2, A3
├── Organizer B ── Event B1, B2
└── Organizer C ── Event C1
```

Bukan website single-event. Data antar organizer terisolasi penuh (tenant isolation).

## Stack

| Lapisan    | Teknologi                                  |
|------------|--------------------------------------------|
| Backend    | Laravel 13, PHP 8.4, Blade, Livewire 4.4+ |
| Interaksi  | Alpine.js, Tailwind CSS 4                  |
| RBAC       | Spatie Laravel Permission                  |
| Database   | PostgreSQL 16+                             |
| Queue      | Database (MVP) → Redis bila traffic naik   |
| Storage    | Local filesystem → S3/R2 via abstraksi     |
| Web server | Nginx + PHP-FPM                            |
| Test       | Pest, Pint, PHPStan/Larastan               |

Tanpa React/Vue SPA. Server-rendered agar hemat resource VPS.

## Status

**Phase 3 — Selesai.** Volunteer profile + registration + custom fields + seleksi + quota atomik + suite hijau. Lanjut Phase 4 (Operations).
Lihat [PRD.md](PRD.md) dan peta dokumen di bawah.

## Quickstart Dev (rencana, setelah scaffold Phase 0)

```bash
# 1. Pastikan Docker tersedia
# 2. Scaffold Laravel 13 + PHP 8.4 ke subdirektori lalu pindahkan
#    (create-project menolak direktori tidak kosong; lihat DEPLOYMENT.md)
composer create-project laravel/laravel:^13.0 webvolunteer-tmp
# 3. Jalankan PostgreSQL dev via Docker
# 4. cp .env.example .env && php artisan key:generate
# 5. php artisan migrate && php artisan test
```

Detail environment: [DEPLOYMENT.md](DEPLOYMENT.md).

## Peta Dokumen

| Dokumen              | Isi                                                                 |
|----------------------|---------------------------------------------------------------------|
| [PRD.md](PRD.md)                 | Vision, user types, fitur per phase, state transition, business rules |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Modular monolith, service layer, abstraksi queue/storage/cache   |
| [DATABASE.md](DATABASE.md)         | Tabel, relasi, constraint, index, ERD                            |
| [SECURITY.md](SECURITY.md)         | Auth, RBAC+scope, tenant isolation, auth matrix, threat model    |
| [TESTING.md](TESTING.md)           | Strategi unit/feature/authorization/isolation/concurrency        |
| [API.md](API.md)                   | Kontrak `/api/*` (dibangun setelah fondasi web stabil)           |
| [DEPLOYMENT.md](DEPLOYMENT.md)     | Docker dev, VPS prod, backup/restore, hardening checklist        |

## Development Phases

1. **Foundation** — auth, user, organization, membership, RBAC, tenant isolation
2. **Event** — event, division, role, shift, public event page
3. **Volunteer** — profile, registration, custom fields, seleksi, waitlist, quota
4. **Operations** — assignment, schedule, QR attendance, announcement, notification
5. **Advanced Event** — artist liaison, incident, lost & found, talent pool, certificate
6. **Reporting** — analytics, export, dashboard
7. **Hardening** — security audit, isolation test, concurrency test, production hardening

## Prinsip Non-Negosiable

```text
Security First → Authorization First → Tenant Isolation →
Server-side Validation → Database Integrity →
Transaction & Concurrency Safety → Business Logic →
Performance → UX → Testing
```

- Jangan percaya data/role/tenant ID/event ID dari client.
- Authorization selalu di backend (Policy + scoped query).
- Critical write selalu dalam transaction + constraint database.
- Tidak ada klaim "100% aman" — secure-by-design, testing, review, monitoring.
