# API

Status: **kontrak desain**. API dibangun setelah fondasi web (Phase 1–4)
stabil. Sanctum diadopsi saat itu (YAGNI sampai sini). Dokumen ini mengunci
struktur, konvensi, dan keamanan agar implementasi tinggal mengikuti.

## 1. Struktur Endpoint

```text
/api/auth/*                         # login, logout, refresh, me
/api/organizations/*                # profil org (scoped membership)
/api/events/*                       # public list/detail + scoped manage
/api/events/{event}/divisions/*
/api/events/{event}/roles/*
/api/events/{event}/shifts/*
/api/events/{event}/registrations/*
/api/events/{event}/assignments/*
/api/events/{event}/attendance/*
/api/notifications/*                # milik sendiri
/api/reports/*                      # event-scoped, permission report.export
/api/certificates/verify/{no}       # publik, data minimal
/api/admin/*                        # super_admin only
```

`{event}` selalu di-resolve dalam scope organisasi pemanggil; di luar
scope → 404. Tidak ada endpoint yang menerima `organization_id`/
`event_id` dari body sebagai sumber kebenaran — scope berasal dari
server context (token + path binding).

## 2. Auth & Keamanan per Endpoint

- Semua endpoint (kecuali list/detail publik + verifikasi sertifikat):
  `auth:sanctum` + `abilities` minimal + Policy + throttle.
- Throttle default `60/min`; sensitif lebih ketat: auth `5/mnt`,
  registration submit `10/mnt`, QR scan `30/mnt`, export `5/jam`,
  broadcast `5/jam`.
- Validasi via Form Request; pagination wajib (`per_page` max 100);
  tanpa `SELECT *` — resource hanya memuat field yang dibutuhkan
  (public vs private resource terpisah).
- Idempotency: `Idempotency-Key` header pada POST registration,
  check-in/out, export. Key sama → respons record existing (200),
  bukan duplikat.

## 3. Konvensi Response

Sukses list:

```json
{ "data": [...], "meta": { "current_page": 1, "total": 250 } }
```

Sukses mutasi: `201 + resource` (create), `200 + resource` (update),
`200 + { "message": "..." }` (aksi). Error aman:

| Kode | Arti |
|---|---|
| 400 | Bad request (validasi lolos tapi bisnis menolak — gunakan 422 bila validasi) |
| 401 | Unauthenticated |
| 403 | Forbidden (dalam scope tapi tak berhak) |
| 404 | Not found / di luar scope (tidak membocorkan) |
| 409 | Konflik (duplikat, replay QR, quota penuh) |
| 422 | Validation error (`{ "errors": { "field": [...] } }`) |
| 429 | Rate limited |
| 500 | Generic + `error_id` (tanpa trace/SQL/path/secret) |

## 4. Contoh Kontrak (ringkas)

- `GET /api/events?category=&city=&date=&q=` → publik, paginated,
  field publik saja.
- `POST /api/events/{event}/registrations` → auth volunteer,
  body: `role_id` + `answers[]`; respons 201 (Pending) / 409 (duplikat).
- `POST /api/events/{event}/registrations/{id}/accept` → auth staff
  + `registration.review`; 200 / 409 (quota penuh → sarankan waitlist).
- `POST /api/events/{event}/attendance/check-in` → auth volunteer
  (QR token) atau staff + `attendance.manage`; validasi time window.
- `GET /api/certificates/verify/{no}` → publik: event, nama, role,
  tanggal selesai. Tanpa kontak/data privat.
- `POST /api/events/{event}/exports` → staff + `report.export`;
  respons 202 + job ID; unduh setelah job selesai (link signed, expiry).

## 5. Versioning & Evolusi

Prefix `/api/v1/` saat rilis pertama; breaking change → `/v2/` +
deprecation window. Web/Livewire dan API memakai service yang sama —
tidak ada duplikasi business logic.
