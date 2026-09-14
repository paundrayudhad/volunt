# Phase 0: Dev Scaffold Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mendirikan fondasi dev yang berjalan: Docker (PHP 8.4 + PostgreSQL 16 + Nginx), scaffold Laravel 13, paket dasar terinstall, test suite hijau.

**Architecture:** Semua perintah PHP/composer/artisan berjalan di container `app` (image `php:8.4-fpm`), menutup gap PHP host 8.2. Build asset via Node host (v24 tersedia). Database dev + test di container `db`. Tanpa Redis/service lain.

**Tech Stack:** Docker Compose, PHP 8.4-FPM, PostgreSQL 16-alpine, Nginx alpine, Laravel ^13.0, Livewire ^4.0, Spatie Permission, Pest, Pint, Larastan, Node 24 (host, hanya untuk build asset).

**Spec:** `README.md`, `PRD.md`, `ARCHITECTURE.md`, `DATABASE.md`, `SECURITY.md`, `TESTING.md`, `API.md`, `DEPLOYMENT.md` (repo root `D:\volunt`).

## Global Constraints

- PHP 8.4 — hanya via container; PHP host 8.2 tidak dipakai untuk PHP/composer/artisan.
- Laravel ^13.0 (`composer create-project laravel/laravel:^13.0`).
- PostgreSQL 16 (`postgres:16-alpine`); kredensial dev `webvolunteer/secret`.
- Semua perintah PHP/composer/artisan via `docker compose exec app ...` (atau `run --rm` bila container belum up).
- Dilarang menambah Redis, MinIO, atau service lain di Phase 0 (lihat DEPLOYMENT.md §5).
- Setup Tailwind 4 penuh ditunda ke rencana Phase 1 (fondasi UI); Phase 0 cukup `npm run build` sukses + homepage 200.
- Bahasa prose: Indonesia; code/identifier/branch English.
- Setiap langkah berisi konten nyata; tanpa TBD/TODO/placeholder.

---

## Scope Check

Spec mencakup 7 phase; rencana ini hanya **Phase 0 (scaffold)** — satu-satunya scope yang menghasilkan software berjalan dan teruji tanpa fondasi lain. Phase 1 (Foundation: auth, organization, RBAC, isolation) dan seterusnya masing-masing mendapat rencana terpisah setelah Phase 0 hijau.

## File Structure

Dibuat di Phase 0 (selain ratusan file hasil scaffold yang tidak didaftar satu per satu):

- `docker-compose.yml` — service `app` (PHP-FPM), `web` (Nginx :8000), `db` (Postgres :5432).
- `docker/php/Dockerfile` — `php:8.4-fpm` + ekstensi pgsql/intl/zip/bcmath + composer.
- `docker/nginx/default.conf` — vhost ke `public/`, `client_max_body_size 20M`.
- `phpstan.neon` — Larastan, level 5, paths `app`.
- `tests/Feature/SmokeTest.php` — homepage 200 + koneksi PostgreSQL.
- Diubah: `.env` (DB pgsql + queue database), `phpunit.xml` (DB test pgsql), `README.md` (status Phase 0).

---

### Task 1: Git init + baseline commit

**Files:**
- Create: repo git di `D:\volunt`
- Test: `git log --oneline`, `git status --porcelain`

**Interfaces:**
- Consumes: dokumen pra-coding yang sudah ada + file rencana ini.
- Produces: repo bersih dengan commit baseline; semua task berikut commit di atasnya.

- [ ] **Step 1: Init repo dan commit baseline**

```bash
git init
git add README.md PRD.md ARCHITECTURE.md DATABASE.md SECURITY.md TESTING.md API.md DEPLOYMENT.md docs/superpowers/plans/2026-09-14-phase0-dev-scaffold.md webvolunteer-prompt.md
git commit -m "docs: pre-coding spec + phase 0 plan"
```

- [ ] **Step 2: Verifikasi repo bersih**

Run: `git log --oneline -n 1; git status --porcelain`
Expected: satu baris commit `docs: pre-coding spec + phase 0 plan`, status kosong.

---

### Task 2: Docker dev environment

**Files:**
- Create: `docker/php/Dockerfile`, `docker/nginx/default.conf`, `docker-compose.yml`
- Test: `docker compose config`, `pg_isready` via exec

**Interfaces:**
- Consumes: Task 1 (repo).
- Produces: service `db` sehat (`db:5432`, user/pass `webvolunteer/secret`, db `webvolunteer`); service `app` dengan PHP 8.4 + composer (belum ada kode Laravel — itu Task 3).

- [ ] **Step 1: Tulis Dockerfile PHP**

`docker/php/Dockerfile`:

```dockerfile
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev libicu-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

- [ ] **Step 2: Tulis vhost Nginx**

`docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- [ ] **Step 3: Tulis docker-compose.yml**

`docker-compose.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: webvolunteer-app
    volumes:
      - ./:/var/www/html
    environment:
      DB_HOST: db
      DB_DATABASE: webvolunteer
      DB_USERNAME: webvolunteer
      DB_PASSWORD: secret
    depends_on:
      db:
        condition: service_healthy

  web:
    image: nginx:alpine
    container_name: webvolunteer-web
    ports:
      - "8000:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: postgres:16-alpine
    container_name: webvolunteer-db
    environment:
      POSTGRES_DB: webvolunteer
      POSTGRES_USER: webvolunteer
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U webvolunteer"]
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  pgdata:
```

- [ ] **Step 4: Validasi config dan nyalakan database**

```bash
docker compose config --quiet
docker compose up -d --build db
docker compose exec db pg_isready -U webvolunteer
```

Expected: `config` tanpa error; `pg_isready` mencetak `accepting connections`.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml docker/php/Dockerfile docker/nginx/default.conf
git commit -m "chore: docker dev env (php 8.4, postgres 16, nginx)"
```

---

### Task 3: Scaffold Laravel 13

**Files:**
- Create: seluruh skeleton Laravel di repo root (via direktori temp, karena `create-project` menolak direktori tidak kosong)
- Test: `php -v`, `php artisan --version`, `curl localhost:8000`

**Interfaces:**
- Consumes: Task 2 (service `app`/`web`/`db`).
- Produces: aplikasi Laravel 13 berjalan di `http://localhost:8000` (HTTP 200), `APP_KEY` terisi, asset ter-build.

- [ ] **Step 1: Scaffold ke direktori temp di container**

```bash
docker compose run --rm app composer create-project laravel/laravel:^13.0 /tmp/scaffold
docker compose exec app sh -c "cp -a /tmp/scaffold/. /var/www/html/ && rm -rf /tmp/scaffold"
```

Expected: `create-project` selesai tanpa error; file skeleton (`artisan`, `public/`, `routes/`) ada di `D:\volunt`.

- [ ] **Step 2: Verifikasi versi PHP dan Laravel**

```bash
docker compose exec app php -v
docker compose exec app php artisan --version
```

Expected: `php -v` memuat `PHP 8.4.`; artisan mencetak `Laravel Framework 13.`.

- [ ] **Step 3: Generate key dan build asset**

```bash
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
npm install
npm run build
```

Expected: `key:generate` mencetak `Application key set successfully`; `npm run build` sukses dan menghasilkan `public/build/manifest.json` (atau `manifest.webmanifest` sesuai versi Vite — yang penting build exit 0).

- [ ] **Step 4: Nyalakan stack dan cek homepage**

```bash
docker compose up -d --build
curl -s -o NUL -w "%{http_code}" http://localhost:8000
```

Expected: `200`. Jika bukan 200, baca `docker compose logs app web` dan perbaiki root cause (bukan workaround) sebelum lanjut.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold laravel 13 + build assets"
```

---

### Task 4: Paket dasar + konfigurasi database

**Files:**
- Modify: `.env`, `phpunit.xml`
- Create: `phpstan.neon`
- Test: `php artisan migrate`, `\dt` via psql, `git check-ignore .env`

**Interfaces:**
- Consumes: Task 3 (Laravel berjalan).
- Produces: Spatie + Livewire + Pest + Pint + Larastan terinstall (versi tercatat); migrasi default + permission hijau di `webvolunteer`; DB test `webvolunteer_test` ada.

- [ ] **Step 1: Install paket runtime dan catat versi**

```bash
docker compose exec app composer require spatie/laravel-permission livewire/livewire:^4.0
docker compose exec app composer show spatie/laravel-permission livewire/livewire | findstr "versions"
```

Expected: install sukses; catat versi mayor yang ter-resolve (Spatie harus mendukung Laravel 13 — jika composer konflik, hentikan task dan laporkan, jangan force).

- [ ] **Step 2: Install tooling dev**

```bash
docker compose exec app composer require --dev pestphp/pest --with-all-dependencies
docker compose exec app composer require --dev pestphp/pest-plugin-laravel laravel/pint larastan/larastan
docker compose exec app ./vendor/bin/pest --init
```

Expected: `tests/Pest.php` ada setelah `--init`.

- [ ] **Step 3: Publish Spatie dan set `.env` database**

```bash
docker compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Lalu di `.env` set tujuh key ini (nilai persis):

```text
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=webvolunteer
DB_USERNAME=webvolunteer
DB_PASSWORD=secret
QUEUE_CONNECTION=database
```

Verifikasi:

```bash
docker compose exec app php artisan tinker --execute="print_r(['db'=>config('database.default'),'queue'=>config('queue.default')]);"
```

Expected: `db => pgsql`, `queue => database`.

- [ ] **Step 4: Migrasi + buat database test**

```bash
docker compose exec app php artisan migrate --force
docker compose exec db psql -U webvolunteer -c "CREATE DATABASE webvolunteer_test;"
docker compose exec db psql -U webvolunteer -d webvolunteer -c "\dt" | findstr "permissions roles"
```

Expected: migrate sukses; `\dt` memuat tabel `permissions` dan `roles` (bukti publish Spatie + migrasi hijau).

- [ ] **Step 5: Pastikan `.env` ter-ignore dan tulis phpstan.neon**

```bash
git check-ignore .env
```

Expected: mencetak `.env`.

`phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 5
    paths:
        - app
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: base packages (spatie, livewire, pest, pint, larastan) + pgsql config"
```

---

### Task 5: Smoke test hijau

**Files:**
- Create: `tests/Feature/SmokeTest.php`
- Modify: `phpunit.xml` (env DB test)
- Test: `./vendor/bin/pest`

**Interfaces:**
- Consumes: Task 4 (DB app + test siap, Pest terinstall).
- Produces: suite hijau sebagai baseline Phase 1; `phpunit.xml` menunjuk ke `webvolunteer_test` via service `db`.

- [ ] **Step 1: Arahkan phpunit ke database test**

Baca `phpunit.xml`, pastikan blok `<php>` memuat enam baris ini (tambah/ubah seperlunya):

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="db"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="webvolunteer_test"/>
<env name="DB_USERNAME" value="webvolunteer"/>
<env name="DB_PASSWORD" value="secret"/>
```

Lalu siapkan skema test:

```bash
docker compose exec app php artisan migrate --database=pgsql --force --env=testing
```

Jika perintah di atas tidak memakai DB test (tergantung koneksi `testing`), alternatif deterministik:

```bash
docker compose exec app sh -c "DB_DATABASE=webvolunteer_test php artisan migrate --force"
```

Expected: tabel ada di `webvolunteer_test` (cek: `docker compose exec db psql -U webvolunteer -d webvolunteer_test -c "\dt" | findstr migrations`).

- [ ] **Step 2: Tulis smoke test**

`tests/Feature/SmokeTest.php`:

```php
<?php

use Illuminate\Support\Facades\DB;

it('memuat halaman utama', function () {
    $this->get('/')->assertOk();
});

it('terhubung ke PostgreSQL', function () {
    expect(DB::select('select version()'))->not->toBeEmpty();
});
```

- [ ] **Step 3: Jalankan suite**

```bash
docker compose exec app ./vendor/bin/pest
```

Expected: `2 passed` (atau lebih bila ExampleTest bawaan masih ada dan hijau). Merah → perbaiki root cause, bukan skip/hapus test yang gagal.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/SmokeTest.php phpunit.xml
git commit -m "test: smoke baseline (homepage + pgsql connection)"
```

---

### Task 6: Quality gates + tutup Phase 0

**Files:**
- Modify: `README.md` (status Phase 0)
- Test: `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `composer audit`

**Interfaces:**
- Consumes: Task 5 (suite hijau).
- Produces: Pint + PHPStan + audit hijau; README akurat; Phase 0 selesai dan siap jadi basis rencana Phase 1.

- [ ] **Step 1: Format check**

```bash
docker compose exec app ./vendor/bin/pint --test
```

Expected: `PASS`. Jika `FAIL`, jalankan `./vendor/bin/pint` (tanpa `--test`), lalu ulangi `--test` hingga PASS.

- [ ] **Step 2: Static analysis**

```bash
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

Expected: `No errors`. Error di file skeleton bawaan → perbaiki jika milik kita, atau laporkan sebagai temuan (jangan tambah ke baseline ignore tanpa alasan tertulis di commit message).

- [ ] **Step 3: Dependency audit**

```bash
docker compose exec app composer audit
```

Expected: tanpa temuan critical/high. Jika ada: update paket terkait, atau catat alasan penundaan di commit message (lihat SECURITY.md §DEPLOYMENT checklist).

- [ ] **Step 4: Update status README dan commit final**

Ubah di `README.md`:

```text
Lama: **Phase 0 — Pra-coding (dokumen desain).** Belum ada kode aplikasi.
Baru: **Phase 0 — Selesai.** Scaffold Laravel 13 + Docker dev + suite hijau. Lanjut Phase 1 (Foundation).
```

```bash
git add -A
git commit -m "chore: phase 0 done (gates green) — ready for phase 1"
git log --oneline -n 6
```

Expected: 6 commit Phase 0 terlihat (docs, docker, scaffold, packages, smoke, done).

---

## Self-Review

1. **Spec coverage:** Rencana ini hanya mengeksekusi DEPLOYMENT.md §3 (Docker dev) + prasyarat tooling TESTING.md (Pest/Pint/PHPStan). Auth, tenancy, RBAC, domain event, dan seluruh modul lain eksplisit di luar scope — masing-masing menunggu rencana Phase 1+. Tidak ada gap dalam Phase 0.
2. **Placeholder scan:** Setiap langkah berisi perintah/file/expected output konkret. Satu-satunya titik adaptasi (versi mayor Spatie, baris `phpunit.xml`, perintah migrate env testing) memiliki instruksi deterministik + kriteria berhenti. Catatan penundaan (Tailwind penuh, queue worker, Sanctum) adalah keputusan scope eksplisit, bukan placeholder.
3. **Type consistency:** Nama service (`app`, `web`, `db`), kredensial (`webvolunteer/secret`), nama DB (`webvolunteer`, `webvolunteer_test`), dan key `.env` identik di semua task. Nama file rencana konsisten (`2026-09-14-phase0-dev-scaffold.md`).
