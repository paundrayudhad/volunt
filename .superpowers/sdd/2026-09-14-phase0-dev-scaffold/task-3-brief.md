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

