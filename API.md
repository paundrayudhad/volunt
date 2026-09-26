# API

Status: **Diimplementasikan (Phase 8 - Core Volunteer & Public REST API)**.

API dibangun di atas arsitektur stateless dengan otentikasi **Laravel Sanctum** Bearer Token untuk endpoint privat dan Named Rate Limiters untuk seluruh rute.

## 1. Struktur Endpoint Aktif (`/api/v1/*`)

```text
/api/v1/ping                           # Health check / ping
/api/v1/auth/login                     # Login & issue Bearer token (throttle:auth, 5/min)
/api/v1/auth/me                        # Profil user aktif (auth:sanctum)
/api/v1/auth/logout                    # Revoke current token (auth:sanctum)

/api/v1/events                         # Public event catalog (throttle:public-api, 60/min)
/api/v1/events/{slug}                  # Public event detail + roles & shifts (throttle:public-api)

/api/v1/events/{slug}/register         # Submit volunteer registration (auth:sanctum, throttle:registration-submit, Idempotency-Key)
/api/v1/my/registrations               # Riwayat pendaftaran pribadi (auth:sanctum)
/api/v1/my/registrations/{id}/withdraw # Penarikan diri dari pendaftaran (auth:sanctum)

/api/v1/certificates/verify/{no}       # Verifikasi publik sertifikat tanpa PII (throttle:public-api, 60/min)
```

## 2. Keamanan & Rate Limiting

- **`throttle:auth`**: 5 request per menit per IP + email untuk mencegah brute force pada `/api/v1/auth/login`.
- **`throttle:public-api`**: 60 request per menit per IP untuk katalog event publik dan verifikasi sertifikat.
- **`throttle:registration-submit`**: 10 submit per menit per user/IP pada pendaftaran relawan.
- **Fail-Closed Multi-Tenant & Privacy Isolation**:
  - Event non-publik (status `draft`) mengembalikan **404 Not Found**.
  - Akses atau penarikan pendaftaran milik user lain mengembalikan **404 Not Found**.
  - Verifikasi sertifikat publik hanya mengembalikan data publik (Nomor, Nama Penerima, Event, Organisasi, Role) dan tidak membocorkan PII (email/telepon).

## 3. Format Response Standar

### Sukses List
```json
{
  "data": [ ... ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 10
  }
}
```

### Sukses Detail & Mutasi
```json
{
  "data": { ... },
  "message": "Pendaftaran berhasil diajukan."
}
```

### Error Matrix
| Kode | Kondisi | Contoh Payload |
|---|---|---|
| 401 | Kredensial login salah / unauthenticated | `{"message": "Kredensial tidak cocok dengan catatan kami."}` |
| 404 | Resource tidak ditemukan / di luar otorisasi | `{"message": "Pendaftaran tidak ditemukan."}` |
| 409 | Duplikat pendaftaran / kuota penuh | `{"message": "Anda sudah memiliki pendaftaran aktif pada event ini."}` |
| 422 | Kesalahan validasi | `{"message": "Data tidak valid.", "errors": { ... }}` |
| 429 | Rate limit terlampaui | `{"message": "Too Many Attempts."}` |
