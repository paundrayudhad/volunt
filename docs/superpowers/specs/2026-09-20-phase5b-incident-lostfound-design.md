# Phase 5B (Incident + Lost & Found) — Design Spec

**Status:** Disetujui per seksi (Seksi 1–5) pada 2026-09-20.
**Scope:** pelaporan & penanganan insiden event (kategori medical/security/crowd/technical/lost&found/other, prioritas low–critical, rantai open→assigned→in_progress→resolved→closed + reopen), pelaporan barang hilang-temu (kind lost/found, satu foto per item, alur klaim ringan + persetujuan handler), penghubung insiden↔item, halaman organizer + volunteer. (PRD §10, §11 Phase 5; DATABASE.md §Modul Event Musik + §relasi; SECURITY.md §9; TESTING.md §2–§8)
**Non-scope:** sertifikat (5A), artist liaison (5C), talent pool (5D), export/analytics/dashboard (Phase 6); galeri publik lost&found; notifikasi real-time/eskalasi otomatis; klaim multi-pihak/sengketa; retensi arsip lintas event (utang 5D).

Keputusan kunci (hasil klarifikasi 2026-09-20): relasi terpisah-tapi-terhubung (`incidents.lost_found_item_id` nullable, tanpa cascade silang — hapus satu sisi menullkan link, sisi lain tetap ada); pelapor = semua role terautentikasi (organizer/staff ber-permission, volunteer event tersebut); rantai status penuh PRD + reopen beralasan; satu foto per item (max 5MB, jpg/png/webp, serve via route signed 30 menit); critical = flag visual saja (tanpa eskalasi otomatis); klaim pendekatan A (tanpa tabel claims: tombol Klaim → `claimed` + claimant → handler setuju `returned` / tolak kembali `found`); halaman organizer + volunteer (tanpa galeri publik).

## 1. Arsitektur & Komponen

Dua domain baru yang terhubung, menempel pada `Event`: **`Incident`** (rantai status + history) dan **`LostFoundItem`** (kind + klaim). `IncidentService` dan `LostFoundService` masing-masing satu-satunya penulis tabelnya (pola `AssignmentService`/`CertificateService`).

Alur lapisan (sama seperti Phase 1–5A): Controller tipis → Form Request (`authorize()` + `rules()`) → Service → Model. Seluruh business logic di service.

| Service | Tanggung jawab |
|---|---|
| `IncidentService` | Satu-satunya penulis `incidents` + `incident_status_histories`: `report()`, `assign()`, `transition()` (maju-satu-langkah, 422 bila lompat/mundur), `reopen()` (dari resolved/closed → open + alasan, 422 dari state lain). Critical = flag visual (badge) tanpa perilaku khusus |
| `LostFoundService` | Satu-satunya penulis `lost_found_items`: `report()` (kind lost/found + 1 foto), `claim()` (found→claimed + claimant_id/claimed_at; tolak ganda/milik-sendiri), `resolveClaim()` (claimed→returned bila setuju / →found + claimant dibersihkan bila tolak), `close()` |

Penghubung: `incidents.lost_found_item_id` nullable → lost_found_items, `nullOnDelete`, tanpa cascade silang. Show dua arah (insiden menampilkan item tertaut dan sebaliknya).

Model otorisasi (pola Phase 1–5A): permission Spatie = **kemampuan**; membership = **cakupan** (`can()` + `belongsToOrganization()`). Owner mendapat permission via sinkronisasi `MembershipService`; `STAFF_BASE` tetap view-only.

Permission granular baru: `incident.manage` (assign, transition, reopen, destroy), `incident.report` (store insiden + store lost&found — relawan posko boleh mencatat), `lostfound.manage` (resolve-claim, close, destroy lost&found). Volunteer own-scoped via `reporter_id = auth()->id()` server-side.

Throttle: store insiden/lost&found 30/menit (konsisten bulk Phase 3–4); foto signed URL kedaluwarsa 30 menit; tanpa `password.confirm` (bukan aksi sensitif destruktif permanen — soft-delete).

## 2. Data & Migrasi

Tabel baru (kolom mengikuti DATABASE.md §Modul Event Musik + §relasi):

- **`incidents`** — `event_id`, `category` (check: medical, security, crowd, technical, lost_found, other), `priority` (check: low, medium, high, critical), `location`, `description`, `attachment_path` (nullable, lampiran opsional), `reporter_id → users`, `assignee_id → users` (nullable), `status` (check: open, assigned, in_progress, resolved, closed; default open), `lost_found_item_id` (nullable → lost_found_items, null on delete), SoftDeletes. Index: `(event_id, status)`, `(event_id, priority)`. Check constraints via `DB::statement`.
- **`lost_found_items`** — `event_id`, `kind` (`lost`/`found`), `item_name`, `description`, `photo_path` (nullable), `location`, `occurred_at`, `reporter_id → users`, `handler_id → users` (nullable), `claimant_id → users` (nullable), `claimed_at` (nullable), `status` (check: `open`/`found`/`claimed`/`returned`/`closed` — `open` = laporan lost belum ketemu, `found` = barang ada di posko). Index `(event_id, status)`, `(event_id, kind)`.
- **`incident_status_histories`** — `incident_id`, from/to, actor, note, created_at. Append-only (jejak rantai status wajib diaudit, pola AssignmentHistory). Klaim lost&found tercatat via audit_logs (tanpa tabel history khusus — status item jarang berubah).

Relasi: `Event hasMany Incident` + `hasMany LostFoundItem`; `User` hasMany keduanya sebagai reporter; `Incident belongsTo LostFoundItem` (nullable) + `hasMany IncidentStatusHistory`; `LostFoundItem hasMany Incident` (sisi balik link).

Kolom sensitif (`event_id`, `reporter_id`, `claimant_id`, `handler_id`, `claimed_at`, counter) tidak fillable — via service.

Aturan idempotensi: double-submit store mengandalkan validasi + unique key alami bila ada (tanpa token idempotency khusus — YAGNI, pola store Phase 2–4).

## 3. Alur Request & Otorisasi

Route groups (pola Phase 1–5A, binding scoped org → event → resource; luar scope → 404; terlihat tapi tak diizinkan → 403):

- **Organizer** `/organizer/{organization}/events/{event}/incidents` — index (filter status/priority/category) + show (termasuk history rantai) + store + assign (`assignee_id` — wajib member organisasi yang sama, validasi server-side) + transition (`POST .../{incident}/transition` dengan `to` — service validasi maju-satu-langkah) + reopen (`POST .../{incident}/reopen` dengan alasan → kembali `open`) + destroy (soft-delete). Policy: member + `incident.report` (store) / `incident.manage` (assign, transition, reopen, destroy).
- **Organizer** `/organizer/{organization}/events/{event}/lost-found` — index (filter kind/status) + show + store (dengan foto) + claim-decision (`POST .../{item}/resolve-claim` dengan `decision: returned|rejected` + catatan; hanya bila status `claimed`) + close (dari `found`/`returned` → `closed`) + destroy (soft-delete). Policy: member + `lostfound.manage` (semua kecuali store yang cukup `incident.report`).
- **Volunteer** (auth + verified, own-scoped): `GET /my/incidents` + `POST /my/incidents` (lapor, event-id dari form, wajib event yang ia ikuti — validasi server-side via registration accepted) + `GET /my/lost-found` + `POST /my/lost-found` (lapor lost/found + 1 foto) + `POST /my/lost-found/{item}/claim` (tombol Klaim → status `claimed`; hanya item `found` tanpa claimant; milik sendiri tak bisa diklaim).
- **Foto item**: `GET /lost-found-photos/{item}` — route signed sementara (`URL::signedRoute`, kedaluwarsa 30 menit), controller cek membership event + signature; tanpa URL publik langsung ke storage.

Aturan transaksi (`DB::transaction`): transition + history + audit; claim + `claimed_at` + audit; resolve-claim + status + audit. Semua input via Form Request; `$fillable` allowlist; `*_id` server-side (reporter = auth, claimant = auth, handler = auth saat resolve).

IDOR (SECURITY.md §4): tenant di luar scope → 404; aksi terlihat tapi tak diizinkan → 403.

## 4. Upload & Keamanan File

Mengikuti SECURITY.md §9 (aturan upload proyek):

- **Satu foto per item** (`photo_path`, nullable): validasi Form Request `image|mimes:jpg,jpeg,png,webp|max:5120`. Lampiran insiden (`attachment_path`, nullable, opsional): aturan sama — teks murni tanpa lampiran tetap valid.
- **Penyimpanan**: disk `local` via `Storage` (direktori non-executable, di luar docroot — tanpa URL publik langsung). Nama file = UUID + ekstensi dari allowlist (jangan percaya filename asli); tolak traversal/overwrite (nama selalu digenerate, tak ada input path dari user).
- **Re-encode image**: muat ulang via GD/Imagick lalu tulis ulang (melucuti payload tersembunyi dalam EXIF/metadata) — helper tunggal `StoredPhoto::fromUpload()` di service agar logikanya satu tempat.
- **Serve**: hanya via `GET /lost-found-photos/{item}` (signed URL 30 menit + cek membership event). Controller `abort(404)` untuk item tanpa foto; `Content-Type` dari allowlist, `Content-Disposition: inline`. Tanpa `php`/`svg` — SVG ditolak (vektor eksekusi script).
- **Replace & delete**: upload baru → hapus file lama dari Storage (tanpa file yatim); soft-delete record → file dipertahankan (audit/retensi); force-delete (bila ada) → hapus file.
- **Batas**: 5MB; dimensi max 4096px sisi panjang (tolak gambar raksasa anti-dekompresi-bomb); validasi dimensi server-side.

## 5. Testing & Gates

TDD per task (helper prefix per file, pola Phase 1–5A):

- **Rantai status**: open→assigned→in_progress→resolved→closed tiap langkah OK; lompat langkah → 422; mundur tanpa reopen → 422; reopen dari resolved/closed → open + history; reopen dari open → 422; tiap transisi → 1 baris history append-only + audit.
- **Klaim**: klaim item `found` → `claimed` + claimant/claimed_at + audit; klaim item bukan-`found` (termasuk klaim ganda) → 404 'Barang tidak tersedia untuk diklaim.' (IDOR: resource tak tersedia bagi peminta); klaim milik sendiri → 422; setuju → `returned`; tolak → `found` + claimant terhapus; resolve saat bukan `claimed` → 422; non-handler → 403.
- **Penghubung**: insiden kategori lost_found + item tertaut → show dua arah; hapus item → insiden tetap ada (link null); hapus insiden → item tetap ada.
- **Foto**: non-image → 422; >5MB → 422; svg → 422; signed URL kedaluwarsa/diutak-atik → 403; lintas org → 404; upload baru hapus file lama; record tanpa foto → 404.
- **Otorisasi** (gaya OperationsAuthorizationTest): staff read-only 403 semua mutasi; lintas org/event 404; guest → login; volunteer lapor OK tapi assign/transition/close → 403.
- **Isolasi** (gaya RegistrationIsolationTest): dua org independen, simetris.
- **Backfill perm** (pelajaran Critical 5A): migrasi memberi `incident.manage|report` + `lostfound.manage` ke semua owner lama; test khusus backfill (owner lama dapat perm, non-owner tak tersentuh).

Gates (konsisten Phase 1–5A): Pest hijau penuh; Pint; Larastan level 5; `composer audit` bersih; prose Indonesia + identifier English; konten nyata tanpa placeholder; `git add` eksplisit; commit trailer `Co-Authored-By: Claude Code <noreply@anthropic.com>`; controller tipis → Form Request → Service → Model; Docker via `docker compose exec app`.
