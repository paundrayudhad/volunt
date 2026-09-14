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
3. Deploy: `composer install --no-dev`, `npm run build`,
   `php artisan migrate --force`, cache config/route/view,
   `storage:link`, permission `storage/` + `bootstrap/cache/`.
4. Supervisor/systemd untuk `queue:work` + `schedule:run`;
   logrotate; firewall hanya 80/443 (+ SSH key-only).
5. Migration selalu reversibel bila memungkinkan, tested di staging,
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

## 6. Security Headers (Nginx/Laravel, produksi)

HSTS (`max-age` besar + preload setelah validasi), CSP ketat
(`default-src 'self'` + allowlist asset), `X-Content-Type-Options:
nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` minimal, `X-Frame-Options: DENY`, cookie Secure.

## 7. Backup & Recovery

- `pg_dump` terjadwal (harian, full + WAL bila managed), retensi:
  harian 7, mingguan 4, bulanan 6. Backup file storage (snapshot /
  sync ke object storage).
- Prosedur restore terdokumentasi + **diuji berkala di staging** —
  backup yang belum pernah diuji restore tidak dianggap valid.
- Skema retensi data aplikasi (registration, attendance, log, file)
  configurable; hard delete hanya via artisan teraudit oleh Super Admin.

## 8. Observability

Request ID per request (middleware + log context), application log,
`audit_logs` + `security_logs`, error tracking (mis. Sentry/Flare),
queue monitoring (gagal + retry), slow-query log PostgreSQL.
Tanpa secret di log mana pun.

## 9. Production Hardening Checklist (wajib sebelum go-live)

- [ ] `APP_DEBUG=false`, error generik + error ID
- [ ] HTTPS + HSTS + headers §6 + cookie Secure/HttpOnly/SameSite
- [ ] Rate limit aktif semua endpoint sensitif
- [ ] `composer audit` + `npm audit` bersih (critical/high)
- [ ] Pint + PHPStan hijau; seluruh suite TESTING.md hijau
- [ ] Backup + restore test lolos; runbook insiden tersedia
- [ ] `.env` tidak di repo; secrets via manager; `.env.example` lengkap
- [ ] Dependency tak terpakai dibuang; lock file di-commit
