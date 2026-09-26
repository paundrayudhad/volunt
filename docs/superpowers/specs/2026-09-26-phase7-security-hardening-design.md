# Phase 7: Security Audit, Production Hardening & Final Verification Design

Dokumen spesifikasi teknis untuk implementasi penguatan keamanan (*defense-in-depth*), konfigurasi kesiapan produksi, *security headers*, *rate limiting*, *production health audit*, dan verifikasi menyeluruh isolasi multi-tenant pada platform WebVolunteer.

---

## 1. Arsitektur Keamanan & Scope

Phase 7 berfokus pada pengamanan platform di seluruh lapisan (HTTP transport, session, routing, tenant isolation, database, dan operational deployment):

```text
Incoming Request
  │
  ▼
[SecurityHeaders Middleware] ── (CSP, HSTS, X-Frame-Options, Nosniff, Referrer, Permissions)
  │
  ▼
[RateLimiter Enforcers] ────── (auth, registration-submit, exports, public-api)
  │
  ▼
[Authentication & Session] ─── (Secure Cookie, HttpOnly, SameSite, Regenerate ID)
  │
  ▼
[Multi-Tenant & Policy Guard]─ (Org Scoping, IDOR Protection -> 404 Fail-Closed)
  │
  ▼
[Application & DB Integrity] ─ (Transactions, SELECT FOR UPDATE, Sanitized Logging)
```

---

## 2. Komponen Teknis

### 2.1 Global Security Headers Middleware (`app/Http/Middleware/SecurityHeaders.php`)

Middleware ini diaktifkan secara global di `bootstrap/app.php` setelah `RequestId::class`.

#### Headers yang Ditetapkan:
1. **`X-Frame-Options`**: `DENY` (mencegah clickjacking pada seluruh halaman).
2. **`X-Content-Type-Options`**: `nosniff` (mencegah MIME-type sniffing).
3. **`Referrer-Policy`**: `strict-origin-when-cross-origin` (melindungi privasi URL referer antar origin).
4. **`Permissions-Policy`**: `camera=(), microphone=(), geolocation=(), payment=(), usb=()` (menonaktifkan API browser yang tidak relevan).
5. **`Content-Security-Policy (CSP)`**:
   ```text
   default-src 'self';
   script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net;
   style-src 'self' 'unsafe-inline' https://fonts.bunny.net;
   font-src 'self' https://fonts.bunny.net data:;
   img-src 'self' data: blob: https:;
   connect-src 'self';
   frame-ancestors 'none';
   form-action 'self';
   base-uri 'self';
   ```
6. **`Strict-Transport-Security (HSTS)`**:
   - Jika request berjalan via HTTPS atau `app()->environment('production')`:
   - `max-age=31536000; includeSubDomains; preload`.

---

### 2.2 Rate Limiting Terpadu (`app/Providers/AppServiceProvider.php`)

Konfigurasi named rate limiters menggunakan `RateLimiter::for()`:

```php
// 1. Auth Rate Limiter (Login, Forgot Password, Reset Password)
RateLimiter::for('auth', function (Request $request) {
    $email = (string) $request->input('email');
    return Limit::perMinute(5)->by($request->ip().'|'.$email);
});

// 2. Volunteer Registration Submission
RateLimiter::for('registration-submit', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});

// 3. Sensitive Data Exports (CSV & XLSX)
RateLimiter::for('exports', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});

// 4. Public API & Verification (Catalog & QR Scan Verification)
RateLimiter::for('public-api', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});
```

---

### 2.3 Production Readiness Verification Command (`app/Console/Commands/CheckProductionCommand.php`)

Artisan command `php artisan app:check-production` untuk verifikasi otomatis *pre-flight checklist* sebelum deployment:

#### Parameter Pemeriksaan:
1. **Environment Mode**: Memastikan `APP_ENV === 'production'` dan `APP_DEBUG === false`.
2. **Encryption Key**: Memastikan `APP_KEY` tidak kosong dan berformat valid base64.
3. **Session & Cookie Hardening**: Memastikan `SESSION_SECURE_COOKIE === true` dan `SESSION_HTTP_ONLY === true`.
4. **Database Connectivity**: Memastikan koneksi PostgreSQL aktif dan memeriksa apakah ada pending migrations.
5. **Storage & Cache Permissions**: Memverifikasi direktori `storage/` dan `bootstrap/cache/` dapat ditulis (`is_writable`).
6. **Output Format**: Memberikan output tabel konsol dengan status `[OK]` atau `[FAIL]` dan exit code `0` (lulus) atau `1` (ada kegagalan).

---

## 3. Matriks Pengujian & Verifikasi Keamanan

### 3.1 Suite Pengujian (`tests/Feature/SecurityHardeningTest.php`)
1. **Security Headers Verification**:
   - Menguji bahwa setiap request (guest, auth, organizer, admin) menyertakan `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dan `Content-Security-Policy`.
   - Menguji HSTS header aktif saat environment produksi atau HTTPS.
2. **Rate Limiter Enforcement**:
   - Menguji bahwa pengiriman request pendaftaran/ekspor/auth melebihi batas rate limit menghasilkan respons `429 Too Many Requests`.
3. **Cross-Tenant IDOR & Privilege Escalation Audit**:
   - Memastikan seluruh modul (Events, Divisions, Roles, Shifts, Registrations, Assignments, Attendances, Incidents, Lost & Found, Certificates, Talent Pool, Analytics) menghasilkan `404 Not Found` (fail-closed) saat diakses oleh user dari organisasi lain.
4. **Production Check Command Test**:
   - Menguji bahwa artisan command `app:check-production` mendeteksi konfigurasi `APP_DEBUG=true` sebagai status gagal di production.

---

## 4. Rencana File & Struktur

| File | Status | Deskripsi |
|---|---|---|
| `app/Http/Middleware/SecurityHeaders.php` | Baru | Middleware global untuk menyisipkan security headers |
| `bootstrap/app.php` | Modifikasi | Pendaftaran `SecurityHeaders` middleware |
| `app/Providers/AppServiceProvider.php` | Modifikasi | Konfigurasi named rate limiters (`auth`, `registration-submit`, `exports`, `public-api`) |
| `app/Console/Commands/CheckProductionCommand.php` | Baru | Artisan command audit kesiapan environment produksi |
| `tests/Feature/SecurityHardeningTest.php` | Baru | Comprehensive security and tenant audit test suite |
| `DEPLOYMENT.md` | Modifikasi | Dokumentasi instruksi pre-flight check dan security checklist |
