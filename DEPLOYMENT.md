# DEPLOYMENT

## 1. Topologi

```text
Dev : Docker (PHP 8.4 + PostgreSQL 16 + Nginx) di Windows
Prod: Cloudflare → Nginx → PHP-FPM → Laravel 13 → PostgreSQL
      (+ Redis / object storage bila dibutuhkan, §5)
```

Docker untuk dev agar konsisten dengan target prod dan menutup gap
(PHP lokal 8.2 vs syarat 8.4, tanpa PostgreSQL native). Tanpa container
yang tidak diperlukan — tanpa Redis/MinIO di awal.

## 2. Environment

Pisahkan `local` / `staging` / `production`. Produksi wajib:

```text
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

Secrets (DB password, `APP_KEY`, SMTP, webhook, cloud credential) hanya
via environment/secret manager — tidak pernah di-commit. Sediakan
`.env.example` saat scaffold Phase 0.

## 3. Docker Dev (rencana Phase 0)

- `php:8.4-fpm` + ekstensi: `pgsql`, `mbstring`, `intl`, `gd`, `zip`,
  `redis` (siap pakai, nonaktif default), `pcov` (coverage, dev only).
- `postgres:16-alpine` + volume persisten; database terpisah
  `webvolunteer` / `webvolunteer_test`.
- `nginx:alpine` + config mengarah ke `public/`.
- Satu `docker-compose.yml`: `app`, `web`, `db`, (nanti `queue`
  worker + scheduler). Queue dev = `database`, dijalankan via
  `php artisan queue:work` di container `queue`.

## 4. VPS Produksi (Ubuntu)

1. Nginx + PHP-FPM 8.4 + PostgreSQL 16 (managed lebih baik bila ada).
2. TLS via Cloudflare (Full strict) + HSTS; security headers (§6).
3. Pre-flight check: jalankan `php artisan app:check-production` untuk verifikasi otomatis kesiapan environment.
4. Deploy: `composer install --no-dev`, `npm run build`,
   `php artisan migrate --force`, cache config/route/view,
   `storage:link`, permission `storage/` + `bootstrap/cache/`.
5. Supervisor/systemd untuk `queue:work` + `schedule:run`;
   logrotate; firewall hanya 80/443 (+ SSH key-only).
6. Migration selalu reversibel bila memungkinkan, tested di staging,
   backward-compatible untuk rolling update; tanpa edit manual DB prod
   tanpa migration terdokumentasi.

## 5. Kapan Menambah Layanan

| Sinyal | Tambahan |
|---|---|
| Queue `database` menumpuk / notifikasi lambat | Redis untuk queue |
| Hit rate rendah pada list publik | Redis untuk cache |
| Session file menjadi bottleneck | Redis untuk session |
| Disk upload penuh / butuh CDN | S3/R2 via `Storage` (tanpa ubah logic) |

Tanpa sinyal di atas: jangan tambah. Abstraksi Laravel membuat
perpindahan tanpa mengubah business logic.

## 6. Security Headers & Protection (Laravel Middleware)

Middleware `App\Http\Middleware\SecurityHeaders` terdaftar secara global dan menetapkan:
- **`X-Frame-Options: DENY`**: Mencegah clickjacking pada seluruh halaman.
- **`X-Content-Type-Options: nosniff`**: Mencegah MIME-type sniffing.
- **`Referrer-Policy: strict-origin-when-cross-origin`**: Melindungi privasi URL referer.
- **`Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`**: Menonaktifkan API browser sensitif.
- **`Content-Security-Policy (CSP)`**: Kebijakan CSP ketat membatasi sumber script, style, font, dan frame-ancestors.
- **`Strict-Transport-Security (HSTS)`**: Otomatis aktif saat request HTTPS atau di environment `APP_ENV=production` (`max-age=31536000; includeSubDomains; preload`).

## 7. Rate Limiting Terpadu (Named Rate Limiters)

Sistem menggunakan named rate limiters yang dikonfigurasi di `AppServiceProvider`:
- **`auth`**: 5 percobaan per menit per IP + email untuk mitigasi brute force pada autentikasi.
- **`registration-submit`**: 10 submit per menit per user/IP untuk mencegah spam form pendaftaran.
- **`exports`**: 10 ekspor per menit per user/IP untuk mencegah resource exhaustion pada pengunduhan CSV/XLSX.
- **`public-api`**: 60 request per menit per IP untuk katalog event publik dan verifikasi sertifikat.

## 8. Backup & Recovery

- `pg_dump` terjadwal (harian, full + WAL bila managed), retensi:
  harian 7, mingguan 4, bulanan 6. Backup file storage (snapshot /
  sync ke object storage).
- Prosedur restore terdokumentasi + **diuji berkala di staging** —
  backup yang belum pernah diuji restore tidak dianggap valid.
- Skema retensi data aplikasi (registration, attendance, log, file)
  configurable; hard delete hanya via artisan teraudit oleh Super Admin.

## 9. Observability

Request ID per request (middleware + log context), application log,
`audit_logs` + `security_logs`, error tracking (mis. Sentry/Flare),
queue monitoring (gagal + retry), slow-query log PostgreSQL.
Tanpa secret di log mana pun.

## 10. Pre-Flight Production Checklist (`app:check-production`)

Jalankan artisan command berikut sebelum deployment:

```bash
php artisan app:check-production
```

Checklist pemeriksaan otomatis mencakup:
- [x] `APP_DEBUG=false` saat di environment produksi
- [x] `APP_KEY` valid dan terkonfigurasi
- [x] Koneksi PostgreSQL aktif dan sehat
- [x] Direktori `storage/` dan `bootstrap/cache/` dapat ditulis (`is_writable`)
- [x] `SESSION_SECURE_COOKIE=true` untuk proteksi cookie HTTPS
