# Laporan Task 2: Docker Dev Environment

## Implementasi
Dibuat 3 file sesuai brief verbatim:
- `docker/php/Dockerfile` — `FROM php:8.4-fpm`, ext pdo_pgsql/pgsql/intl/zip/bcmath, composer:2, WORKDIR /var/www/html.
- `docker/nginx/default.conf` — root /var/www/html/public, `try_files ... /index.php?$query_string`, fastcgi_pass app:9000.
- `docker-compose.yml` — service `app` (build php Dockerfile, env DB_*), `web` (nginx:alpine, 8000:80), `db` (postgres:16-alpine, user/pass `webvolunteer/secret`, db `webvolunteer`, healthcheck `pg_isready -U webvolunteer`). Hanya `db` yang di-build/start sesuai brief (app image dibangun di Task 3).

Host php/composer tidak dipakai. Tidak ada Redis/service tambahan.

## Verifikasi
- `docker compose config` → exit 0, tanpa error. (`config --quiet` juga lolos.)
- Docker Desktop awalnya mati (`npipe ... daemon is running`); di-start manual, daemon v29.7.2 UP.
- `docker compose up -d --build db` → `webvolunteer-db Started`.
- `docker compose exec db pg_isready -U webvolunteer` → `/var/run/postgresql:5432 - accepting connections`.
- `docker compose ps` → `webvolunteer-db ... Up (healthy) ... 0.0.0.0:5432->5432/tcp`.

## File berubah
- Baru: `docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf` (74 insertions).

## Self-review
- Isi file identik dengan brief (perintah, kredensial, port, healthcheck).
- Batasan global terpenuhi: PHP 8.4 via container, Postgres 16, tanpa Redis.
- `git status` bersih setelah commit; working tree hanya berisi file brief.

## Concern
- Peringatan CRLF (`LF will be replaced by CRLF`) saat commit — kosmetik autocrlf Windows, isi tidak berubah.
- Service `app`/`web` belum di-build/di-start (sesuai brief; app image pertama kali bisa makan waktu beberapa menit di Task 3).
