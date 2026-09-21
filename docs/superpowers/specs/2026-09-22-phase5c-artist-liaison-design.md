# Phase 5C (Artist + Liaison Officer) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–5) pada 2026-09-22.
**Scope:** manajemen artis/penampil per event + jadwal tampil + LO sebagai penghubung (assignment volunteer→artis), status alur tampil + status kehadiran independen, rider teks bebas + flag terpenuhi, catatan lapangan append-only, validasi konflik otomatis penuh, halaman organizer + LO internal. (PRD §10, §11 Phase 5; DATABASE.md §Modul Event Musik + §relasi; SECURITY.md §9; TESTING.md §2–§8)
**Non-scope:** sertifikat (5A), insiden + lost & found (5B), talent pool lintas event (5D), export/analytics/dashboard (Phase 6); portal artis publik; notifikasi real-time; kontrak/fee artis; rider terstruktur per item.

Keputusan kunci (hasil klarifikasi 2026-09-22): artis event-scoped (tanpa profil global — 5D yang mengglobalkan); LO = assignment volunteer accepted per artis via `artist_liaisons` (bukan role baru); status ganda independen (alur scheduled→soundcheck→performing→done + cancelled vs kehadiran expected→arrived/no_show); kendala LO lewat insiden 5B (tanpa tabel baru); rider teks bebas + flag; catatan append-only; konflik panggung & artis & LO divalidasi otomatis penuh (422); internal organizer + LO saja; permission `artist.manage` / `artist.liaise` (+ `artist.read` untuk staff view-only) + backfill owner & LO lama.

## 1. Arsitektur & Komponen

Satu domain baru menempel pada `Event`: **`Artist`** (jadwal + dua status + rider) dengan pendamping **`ArtistLiaison`** (assignment), **`ArtistStatusHistory`** (jejak dua status), **`ArtistNote`** (catatan lapangan). `ArtistService` satu-satunya penulis keempat tabel (pola `AssignmentService`/`CertificateService`/`IncidentService`).

Alur lapisan (sama seperti Phase 1–5B): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service.

| Service | Tanggung jawab |
|---|---|
| `ArtistService` | Satu-satunya penulis `artists` + `artist_liaisons` + `artist_status_histories` + `artist_notes`: `create()`, `update()`, `transition()` (alur, maju-satu-langkah, 422 bila lompat/mundur), `markAttendance()` (kehadiran, bebas arrived↔no_show), `cancel()` (→cancelled + alasan wajib + slot dilepas), `assignLiaison()` (volunteer accepted di event itu; tolak overlap dampingan), `releaseLiaison()` (soft-delete), `addNote()` (append-only), `toggleRider()` (flag saja), `destroy()` (soft-delete). Cek konflik panggung + artis di setiap tulis jadwal |

Model otorisasi (pola Phase 1–5B): permission Spatie = **kemampuan**; membership = **cakupan** (`can()` + `belongsToOrganization()`). Owner mendapat permission via sinkronisasi `MembershipService`; `STAFF_BASE` tetap view-only (diberi `artist.read` bila perlu baca).

Permission granular baru: `artist.manage` (organizer: CRUD, jadwal, transition, assign/release LO, destroy), `artist.liaise` (LO: update status + kehadiran artis dampingannya, catatan, toggle rider). Volunteer own-scoped via assignment aktif server-side.

Throttle: store/transition 30/menit (konsisten bulk Phase 3–4); tanpa `password.confirm` (bukan aksi sensitif destruktif permanen — soft-delete, konsisten 5B).

## 2. Data & Migrasi

Tabel baru (kolom mengikuti DATABASE.md §Modul Event Musik + §relasi):

- **`artists`** — `event_id`, `name`, `genre` (nullable), `stage` (nullable, nama panggung teks), `scheduled_at` (nullable), `duration_minutes` (nullable, default 60 saat dipakai; validasi 15–240), `performance_order` (nullable, integer, tanpa unique constraint — tiebreak tampilan saja), `contact_name` + `contact_phone` (nullable), `rider_text` (nullable, teks bebas max ~2000), `rider_fulfilled` (boolean default false), `status` (check: scheduled, soundcheck, performing, done, cancelled; default scheduled), `attendance` (check: expected, arrived, no_show; default expected), SoftDeletes. Index: `(event_id, scheduled_at)`, `(event_id, status)`. Check constraints via `DB::statement`.
- **`artist_liaisons`** — `artist_id`, `user_id → users`, SoftDeletes (release = soft-delete; riwayat siapa-pernah-dampingi tetap ada). Unique partial `(artist_id, user_id)` where `deleted_at IS NULL` (satu volunteer satu baris aktif per artis; boleh dampingi >1 artis selama jadwal tak overlap). Index `(user_id)`.
- **`artist_status_histories`** — `artist_id`, `from_status`/`to_status` (nullable sebelah — baris kehadiran mengisi kolom attendance, baris alur mengisi kolom status), `from_attendance`/`to_attendance` (nullable), `actor_id → users`, `note` (nullable, max 500; alasan cancel wajib diisi), `created_at`. Append-only (tanpa update/delete).
- **`artist_notes`** — `artist_id`, `author_id → users` (LO/organizer penulis), `body` (teks, max 2000). Append-only (tanpa update/delete; koreksi = catatan baru).

Relasi: `Event hasMany Artist`; `Artist belongsTo Event` + `hasMany ArtistLiaison` (aktif) + `hasMany ArtistStatusHistory` + `hasMany ArtistNote`; `User` hasMany ketiganya sebagai LO/actor/author.

Kolom sensitif (`event_id`, counter, `*_id` relasi) tidak fillable — via service. `rider_fulfilled` tidak fillable via update umum — hanya via `toggleRider()`.

Aturan idempotensi: double-submit store mengandalkan validasi + unique key alami bila ada (tanpa token idempotency khusus — YAGNI, pola store Phase 2–5B).

Backfill (pelajaran Critical 5A, pola 5B): migrasi memberi `artist.manage` ke semua owner aktif lama + `artist.liaise` ke volunteer accepted yang sudah ada; down() mencabut selektif (manage dari non-owner; liaise hanya dari yang kini BUKAN volunteer accepted DAN bukan owner aktif). Test khusus backfill.

## 3. Alur Request & Otorisasi

Route groups (pola Phase 1–5B, binding scoped org → event → resource; luar scope → 404; terlihat tapi tak diizinkan → 403; paginasi 15; flash Indonesia):

- **Organizer** `/organizer/{organization}/events/{event}/artists` — index (filter status/attendance + urutan jadwal) + show (detail + history + notes + daftar LO) + store + update (jadwal/kontak/rider-teks; validasi konflik) + `POST .../{artist}/transition` (maju-satu-langkah alur atau cancel) + `POST .../{artist}/assign` (`user_id` volunteer accepted di event itu → jadi LO) + `POST .../liaisons/{liaison}/release` (lepas-tugas, soft-delete) + destroy (soft-delete artis). Policy: member + `artist.manage`.
- **LO (volunteer)** `GET /my/liaison` (daftar artis yang ia dampingi aktif) + `POST /my/liaison/{artist}/status` (transition alur + attendance, own-scoped: 404 bila bukan LO-nya) + `POST .../notes` (catatan lapangan) + `POST .../rider` (toggle terpenuhi). Validasi server-side: assignment LO aktif (belum di-release).
- **Konflik jadwal** (service, tolak 422): (a) panggung — artis lain di panggung sama dengan rentang tumpang tindih; (b) artis — jadwal artis yang sama tumpang tindih (untuk multi-slot artis); pengecualian: record yang sedang di-update + soft-deleted tak dihitung. Batas sentuh (end == start) BUKAN konflik.
- **LO bentrok**: volunteer tak bisa jadi LO dua artis yang jadwal dampingannya overlap → 422 (cek di `assignLiaison`).
- Transaksi: mutasi + history + audit dalam `DB::transaction`. Throttle store/transition 30/menit; tanpa `password.confirm` (konsisten 5B — bukan destruktif permanen).

IDOR (SECURITY.md §4): tenant di luar scope → 404; aksi terlihat tapi tak diizinkan → 403.

## 4. Jadwal, Konflik & Rider

Aturan waktu tunggal memakai `scheduled_at` + `duration_minutes` (turunan: `ends_at` = start + durasi, tanpa kolom sendiri):

- **Slot default**: 60 menit bila durasi kosong; validasi `scheduled_at` wajib dalam rentang event (`events.start_date`–`end_date`) + `duration_minutes` 15–240.
- **Matriks konflik** (cek di service, sebelum tulis; hanya bila `scheduled_at` terisi — artis tanpa jadwal tak ikut cek): panggung × rentang **dan** artis × rentang. Batas sentuh (end == start) lolos; yang sedang di-update + soft-deleted dikecualikan. Pesan 422 menyebut pihak yang bentrok ("Bentrok dengan *{nama}* di panggung yang sama 19.00–20.00").
- **Urutan tampil** (`performance_order`, nullable, unik per event): organizer boleh set manual; index default urut `scheduled_at`, lalu `performance_order` sebagai tiebreak tampilan — bukan constraint keras, jadi tak ada 422 dari sini.
- **Rider**: `rider_text` bebas (nullable, max ~2000) ditulis organizer; `rider_fulfilled` boolean default false hanya bisa diubah LO/organizer via toggle khusus (tercatat di audit, bukan history status). LO centang = klaim terpenuhi, bukan edit isi.
- **Batal**: `cancel()` dari state scheduled/soundcheck/performing → `cancelled` + alasan wajib (masuk history); 422 dari `done` (sudah tampil tak bisa dibatalkan); slot jadwal dilepas sehingga tak lagi dihitung dalam cek konflik, tapi record tetap ada (soft-delete tidak dipakai untuk cancel).

## 5. Testing & Gates

TDD per task (helper prefix per file, pola Phase 1–5B):

- **Rantai status alur**: scheduled→soundcheck→performing→done tiap langkah OK; lompat langkah → 422; mundur → 422; cancel dari scheduled/soundcheck/performing → `cancelled` + alasan wajib + slot dilepas; cancel dari done → 422; tiap transisi → 1 baris history append-only + audit.
- **Kehadiran independen**: expected→arrived dan expected→no_show OK; arrived↔no_show bebas bolak-balik; tanpa memengaruhi status alur dan sebaliknya; tiap perubahan tercatat di history yang sama (kolom `from/to` nullable sebelah).
- **Liaison**: assign volunteer accepted di event itu → OK + audit; assign non-volunteer / luar event → 422; assign saat overlap dampingan → 422; release → soft-delete + LO lama jadi 404 di scope-nya; LO update artis orang lain → 404; catatan selalu append-only (edit → 403/404).
- **Konflik**: panggung sama + rentang tumpang tindih → 422 menyebut pihak bentrok; artis sama overlap → 422; end == start lolos; update diri sendiri dikecualikan; soft-deleted/cancelled tak dihitung; jadwal di luar rentang event → 422.
- **Rider**: organizer edit teks OK; LO edit teks → 403; LO toggle fulfilled OK + audit; toggle tanpa assignment aktif → 404.
- **Otorisasi** (gaya OperationsAuthorizationTest): staff read-only 403 semua mutasi; lintas org/event 404; guest → login; volunteer non-LO → 403/404.
- **Isolasi** (gaya RegistrationIsolationTest): dua org independen, simetris.
- **Backfill perm** (pelajaran Critical 5A, pola 5B): migrasi memberi `artist.manage` ke semua owner lama + `artist.liaise` ke volunteer accepted yang sudah ada; test khusus backfill (owner lama dapat perm, non-owner tak tersentuh).

Gates (konsisten Phase 1–5B): Pest hijau penuh; Pint; Larastan level 5; `composer audit` bersih; prose Indonesia + identifier English; konten nyata tanpa placeholder; `git add` eksplisit; commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`; controller tipis → Form Request → Service → Model; Docker via `docker compose exec app`.
