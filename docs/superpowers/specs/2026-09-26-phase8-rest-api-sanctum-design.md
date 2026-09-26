# Phase 8: Core Volunteer & Public REST API (Sanctum) Spec

## 1. Overview & Objectives
Phase 8 memperkenalkan antarmuka REST API stateless berbasis **Laravel Sanctum** untuk sistem sukarelawan modular. API ini melayani integrasi aplikasi mobile / klien pihak ketiga dengan memprioritaskan keamanan (Named Rate Limiters, Bearer Token Abilities, Fail-closed Multi-tenant Authorization, Idempotency-Key) serta menjamin konsistensi business rules dengan menggunakan ulang Domain Service layer yang telah dibangun pada Phase 1–7.

Scope spesifik pada fase ini meliputi:
1. **Sanctum Authentication & Token Management** (`/api/v1/auth/*`)
2. **Public Event Discovery & Catalog** (`/api/v1/events`, `/api/v1/events/{slug}`)
3. **Volunteer Registration & History** (`/api/v1/events/{slug}/register`, `/api/v1/my/registrations`, `/api/v1/my/registrations/{id}/withdraw`)
4. **Public Certificate Verification** (`/api/v1/certificates/verify/{no}`)

---

## 2. Architecture & Design Principles

### 2.1 Routing & Versioning
- Prefix routing: `/api/v1/` yang didefinisikan pada file `routes/api.php` dan didaftarkan melalui `bootstrap/app.php` (`withRouting(api: __DIR__.'/../routes/api.php')`).
- Format Response standar:
  - Sukses List: `{ "data": [...], "meta": { "current_page": 1, "total": 10, "per_page": 15, "last_page": 1 } }`
  - Sukses Detail / Mutasi: `{ "data": { ... }, "message": "..." }`
  - Error: `{ "message": "...", "errors": { ... } }` (HTTP 400, 401, 403, 404, 409, 422, 429).

### 2.2 Reusing Existing Domain Services
Controller API berkarakter *thin* dan mengeksekusi use case melalui service:
- `RegistrationService` & `QuotaService` untuk pemrosesan registrasi (transaksional + `SELECT FOR UPDATE` + audit trail).
- `CertificateService` untuk verifikasi publik nomor sertifikat.
- `EventService` / Eloquent Query Scopes untuk filter katalog event publik.

---

## 3. Detailed Endpoint Contracts

### 3.1 Authentication (`/api/v1/auth/*`)

#### A. `POST /api/v1/auth/login`
- **Middleware**: `throttle:auth` (5 req/menit per IP+Email)
- **Request Body**:
  ```json
  {
    "email": "volunteer@example.com",
    "password": "password",
    "device_name": "Volunteer Mobile App"
  }
  ```
- **Validation**: `email: required|email`, `password: required|string`, `device_name: nullable|string|max:100`
- **Response 200 (OK)**:
  ```json
  {
    "token": "1|abc123xyz...",
    "token_type": "Bearer",
    "user": {
      "id": "uuid/int",
      "name": "John Doe",
      "email": "volunteer@example.com"
    }
  }
  ```
- **Response 401 (Unauthorized)**: `{ "message": "Kredensial tidak cocok dengan catatan kami." }`

#### B. `GET /api/v1/auth/me`
- **Middleware**: `auth:sanctum`
- **Response 200 (OK)**: Menampilkan profil user yang sedang login beserta role/membership organisasi aktif bila ada.

#### C. `POST /api/v1/auth/logout`
- **Middleware**: `auth:sanctum`
- **Action**: Menghapus `currentAccessToken()` dari user.
- **Response 200 (OK)**: `{ "message": "Berhasil logout." }`

---

### 3.2 Public Event Discovery (`/api/v1/events/*`)

#### A. `GET /api/v1/events`
- **Middleware**: `throttle:public-api` (60 req/menit)
- **Query Params**:
  - `q`: string (pencarian nama/deskripsi)
  - `category`: string
  - `city`: string / location search
  - `per_page`: int (max 100, default 15)
- **Filter Ketat**: Hanya menampilkan event dengan `status = 'published'` dan `published_at <= now()`.
- **Response 200 (OK)**:
  ```json
  {
    "data": [
      {
        "id": "uuid",
        "title": "Festival Budaya 2026",
        "slug": "festival-budaya-2026",
        "organization": {
          "name": "Komunitas Budaya",
          "slug": "komunitas-budaya"
        },
        "category": "Culture",
        "start_date": "2026-10-01T08:00:00Z",
        "end_date": "2026-10-03T17:00:00Z",
        "location_type": "offline",
        "location_name": "Taman Kota",
        "city": "Jakarta",
        "quota": 100,
        "available_quota": 45
      }
    ],
    "meta": { "current_page": 1, "total": 1, "per_page": 15, "last_page": 1 }
  }
  ```

#### B. `GET /api/v1/events/{slug}`
- **Middleware**: `throttle:public-api`
- **Filter**: `published` only. Jika tidak ditemukan / masih draft → 404 (Fail-closed).
- **Includes**: Role sukarelawan publik (`event_roles` dengan kuota & deskripsi), shift kerja publik (`event_shifts`), kuesioner kustom form registrasi (`form_fields`).
- **Response 200 (OK)**: Data detail event terisolasi tanpa membocorkan data finansial / kontak internal staff.

---

### 3.3 Volunteer Registrations (`/api/v1/events/{slug}/register` & `/api/v1/my/registrations/*`)

#### A. `POST /api/v1/events/{slug}/register`
- **Middleware**: `auth:sanctum`, `throttle:registration-submit` (10 req/menit)
- **Header Opsional**: `Idempotency-Key: <unique-string>`
- **Request Body**:
  ```json
  {
    "event_role_id": "uuid",
    "answers": [
      {
        "field_id": "uuid",
        "value": "Saya memiliki pengalaman 2 tahun"
      }
    ]
  }
  ```
- **Business Logic**:
  1. Periksa apakah user sudah terdaftar di event ini. Jika ya, return 409 Conflict.
  2. Periksa kuota role/event via `QuotaService`. Jika penuh dan event auto-waitlist → status `waitlist`, jika tidak → 409 Conflict.
  3. Simpan registrasi dan jawaban kuesioner dalam transaksi DB via `RegistrationService`.
- **Response 201 (Created)**:
  ```json
  {
    "data": {
      "id": "uuid",
      "status": "pending",
      "registered_at": "2026-09-26T10:00:00Z",
      "event": {
        "title": "Festival Budaya 2026",
        "slug": "festival-budaya-2026"
      },
      "role": {
        "name": "Liaison Officer"
      }
    },
    "message": "Pendaftaran berhasil diajukan."
  }
  ```

#### B. `GET /api/v1/my/registrations`
- **Middleware**: `auth:sanctum`
- **Response 200 (OK)**: Menampilkan riwayat seluruh pendaftaran yang dimiliki oleh user yang sedang terautentikasi (paginated, urut tanggal terbaru).

#### C. `POST /api/v1/my/registrations/{id}/withdraw`
- **Middleware**: `auth:sanctum`
- **Business Logic**:
  1. Validasi kepemilikan: registrasi harus milik `auth()->id()`. Jika tidak → 404 (Fail-closed).
  2. Validasi status: hanya boleh dibatalkan jika berstatus `pending` atau `approved` sebelum event dimulai.
  3. Mengubah status menjadi `cancelled` dan mengembalikan slot kuota jika sebelumnya `approved`.
- **Response 200 (OK)**: `{ "message": "Pendaftaran berhasil dibatalkan." }`

---

### 3.4 Public Certificate Verification (`/api/v1/certificates/verify/{no}`)

#### A. `GET /api/v1/certificates/verify/{no}`
- **Middleware**: `throttle:public-api` (60 req/menit)
- **Business Logic**:
  - Memanggil `CertificateService::verify($no)`.
  - Hanya menampilkan data non-sensitif: Nomor sertifikat, Nama Penerima, Judul Event, Nama Organisasi Penyelenggara, Peran/Role, dan Tanggal Penerbitan.
  - Jika tidak valid / status `revoked` → 404 Not Found dengan pesan aman.
- **Response 200 (OK)**:
  ```json
  {
    "valid": true,
    "certificate": {
      "certificate_number": "CERT-2026-0001",
      "recipient_name": "Jane Doe",
      "event_title": "Festival Budaya 2026",
      "organization_name": "Komunitas Budaya",
      "role_name": "Liaison Officer",
      "issued_at": "2026-10-04T12:00:00Z"
    }
  }
  ```

---

## 4. Error Handling & Security Matrix

| Skenario | HTTP Status | Format JSON Response |
|---|---|---|
| Kredensial login salah | 401 | `{ "message": "Kredensial tidak cocok dengan catatan kami." }` |
| Token Sanctum kadaluarsa / tidak valid | 401 | `{ "message": "Unauthenticated." }` |
| Mengakses registrasi user lain | 404 | `{ "message": "Data tidak ditemukan." }` |
| Pendaftaran ganda pada event yang sama | 409 | `{ "message": "Anda sudah terdaftar pada event ini." }` |
| Kuota pendaftaran penuh | 409 | `{ "message": "Kuota pendaftaran untuk peran ini telah penuh." }` |
| Rate limit terlampaui | 429 | `{ "message": "Terlalu banyak permintaan. Silakan coba lagi nanti." }` |
| Validasi form request gagal | 422 | `{ "message": "Data yang diberikan tidak valid.", "errors": { ... } }` |

---

## 5. Testing Plan
1. **`AuthApiTest.php`**: Login valid/invalid, Sanctum token creation, ability check, profil `me`, dan logout.
2. **`EventApiTest.php`**: Filter publik status published, 404 pada draft event, parameter sanitization, rate limiting.
3. **`RegistrationApiTest.php`**: Submit pendaftaran, proteksi race-condition kuota, cegah pendaftaran duplikat (409), penarikan diri (withdraw) dan proteksi multi-user isolation (404 bila membajak ID user lain).
4. **`CertificateApiTest.php`**: Verifikasi nomor sertifikat valid vs tidak valid/revoked, jaminan tidak ada data PII (email/telepon) yang bocor pada respon publik.
