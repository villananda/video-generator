# PRD: Aplikasi Generator Video AI (Teks/Gambar → Video)

**Versi:** 1.2 | **Tanggal:** 30 September 2026
**Referensi API:** https://ai.google.dev/gemini-api/docs/omni

**Tech stack:** Laravel (backend) · Inertia.js + Vue 3 (frontend) · Model Gemini Omni Flash (`gemini-omni-1.1-flash`) via Interactions API · Arsitektur Service Pattern

**Perubahan dari v1.1:**

- Tech stack diganti dari Go + Nuxt menjadi Laravel + Inertia + Vue 3.
- Resolusi 360p masuk MVP (P0) sebagai opsi hemat.
- Backend memakai service pattern (controller tipis, logika bisnis di service, integrasi Google di balik interface).
- Panggilan Google memakai HTTP client Laravel ke REST endpoint.
- Proses asinkron memakai Laravel Queue.
- Pengecekan status render memakai polling berkala dari frontend (keputusan MVP).

---

## 1. Ringkasan

Aplikasi web untuk membuat video pendek dari deskripsi teks (Text-to-Video) atau gambar + deskripsi (Image-to-Video). Laravel menerima permintaan, membuat job di queue, memanggil Gemini Omni Flash lewat Interactions API, menyimpan hasil ke storage, lalu halaman Inertia/Vue menampilkan status, pemutar, dan tombol unduh.

## 2. Latar Belakang & Masalah

- Kreator konten dan UMKM butuh video vertikal/lanskap cepat tanpa skill editing.
- Generate video memakan waktu lama (±1–3 menit), jadi tidak boleh menahan request browser.
- API key Google harus tetap di server; pengguna hanya berinteraksi lewat UI sederhana.

## 3. Tujuan & Metrik Sukses

| Tujuan                  | Metrik                                                | Target MVP                       |
| ----------------------- | ----------------------------------------------------- | -------------------------------- |
| Video jadi tanpa friksi | Waktu klik "Buat Video" sampai hasil                  | ≤ 3 menit (P90)                  |
| Keandalan               | Job sukses / total job (di luar blokir safety filter) | ≥ 95%                            |
| Kemudahan               | Pengguna baru menyelesaikan video pertama             | ≥ 70%                            |
| Biaya terkendali        | Biaya API per video                                   | Dipantau harian, ada batas kuota |

**Non-goals MVP:** editor timeline, edit/extend video unggahan, input audio referensi, penghapus watermark, aplikasi mobile native, pembayaran.

## 4. Target Pengguna

1. **Kreator konten:** video 9:16 untuk Reels/TikTok/Shorts.
2. **Pelaku UMKM:** animasi dari foto produk.
3. **Developer:** memanggil generator lewat REST API (P1, Laravel Sanctum).

## 5. Ruang Lingkup Fitur

### 5.1 MVP (P0)

| ID  | Fitur            | Deskripsi                                                                             |
| --- | ---------------- | ------------------------------------------------------------------------------------- |
| F1  | Teks → Video     | Prompt wajib, maks ±1.000 karakter.                                                   |
| F2  | Gambar → Video   | Unggah JPG/PNG/WebP maks 10 MB; gambar dipakai sebagai bingkai pertama; prompt wajib. |
| F3  | Preset prompt    | Chip contoh (pantai senja, kucing lucu, produk, kota neon) mengisi prompt.            |
| F4  | Rasio            | 9:16 dan 16:9 (default API: 16:9).                                                    |
| F5  | Resolusi         | 360p (mode hemat), 720p (default), dan 1080p (upscaled).                              |
| F6  | Mode adegan      | Toggle "Satu adegan tanpa potongan" (menambah instruksi ke prompt, lihat 10.3).       |
| F7  | Audio            | Toggle "Tanpa dialog" dan opsi musik latar (instruksi prompt).                        |
| F8  | Proses asinkron  | Job berjalan di queue; pengguna boleh pindah halaman.                                 |
| F9  | Status & hasil   | Antre / diproses / selesai / gagal, pemutar video, unduh.                             |
| F10 | Penanganan error | Pesan jelas untuk safety filter, timeout, kuota, dan error API; tombol coba lagi.     |

**Catatan resolusi:** 360p dan 720p dirender langsung, sedangkan 1080p dan 4K berupa hasil upscale. 360p cocok untuk pratinjau cepat dan menekan biaya. UI menampilkan label "Hemat" di 360p dan "Upscaled" di 1080p.

### 5.2 P1

| ID  | Fitur                                                                        |
| --- | ---------------------------------------------------------------------------- |
| F11 | Riwayat video per pengguna/perangkat                                         |
| F12 | Login (Laravel Breeze/Socialite: Google/email) dan kuota per akun            |
| F13 | Edit percakapan (`previous_interaction_id`), mis. "ubah pencahayaan"         |
| F14 | Bingkai pertama + terakhir (interpolasi) dan referensi subjek (multi-gambar) |
| F15 | REST API publik dengan token (Sanctum)                                       |
| F16 | Statistik (total video, hari ini), tema gelap/terang                         |

### 5.3 P2

Video extension (10 dtk per langkah, total hingga 40 dtk), resolusi 4K, prompt enhancer/terjemahan otomatis, paket kredit.

## 6. Alur Pengguna Utama

1. Buka halaman **Buat Video**.
2. Pilih mode: **Teks → Video** atau **Gambar → Video**.
3. (Mode gambar) unggah gambar, lihat pratinjau.
4. Tulis deskripsi atau pilih preset.
5. Pilih rasio, resolusi (360p/720p/1080p), dan opsi adegan/audio.
6. Klik **Buat Video**: job dibuat, pengguna diarahkan ke halaman status video.
7. Selesai: video tampil di panel **Hasil**, bisa diputar dan diunduh.
8. Gagal: pesan error dan tombol coba lagi.

## 7. Integrasi Gemini Omni Flash (Interactions API)

Sumber: dokumentasi resmi Gemini Omni Flash. Laravel memanggil REST endpoint langsung lewat `Illuminate\Support\Facades\Http`, tanpa SDK resmi.

### 7.1 Endpoint & model

| Item          | Nilai                                                                                              |
| ------------- | -------------------------------------------------------------------------------------------------- |
| Model         | `gemini-omni-1.1-flash`                                                                            |
| Endpoint REST | `POST https://generativelanguage.googleapis.com/v1beta/interactions` (header `x-goog-api-key`)     |
| Klien HTTP    | `Http::withHeaders([...])->timeout(300)->post(...)`                                                |
| Sinkron       | Satu request mengembalikan hasil setelah video selesai (tanpa operation polling untuk mode inline) |

### 7.2 Pemetaan fitur ke parameter

| Fitur aplikasi     | Parameter API                                                                                                                                                                                                                   |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Teks → Video       | `input`: string prompt                                                                                                                                                                                                          |
| Gambar → Video     | `input`: array `[{type: image, data: base64, mime_type}, {type: text, text: prompt}]`; opsional `generation_config.video_config.task = "image_to_video"`. Awali prompt dengan `<FIRST_FRAME>` agar gambar jadi bingkai pertama. |
| Rasio              | `response_format.aspect_ratio`: `"9:16"` atau `"16:9"`                                                                                                                                                                          |
| Resolusi           | `response_format.resolution`: `360p`, `720p` (default), `1080p` (upscaled), `4k` (upscaled, P2)                                                                                                                                 |
| Video besar        | `response_format.delivery = "uri"` untuk hasil > 4 MB (praktisnya 1080p)                                                                                                                                                        |
| Edit lanjutan (P1) | `previous_interaction_id` dari interaksi sebelumnya                                                                                                                                                                             |

Resolusi 360p dan 720p umumnya berukuran kecil sehingga memakai jalur inline. Jalur URI dipakai untuk 1080p atau bila hasil melebihi 4 MB.

### 7.3 Dua jalur pengambilan hasil

1. **Inline (360p/720p, file kecil):** respons berisi video base64. Decode lalu simpan lewat `Storage`.
2. **URI (1080p, > 4 MB):** respons berisi URI file. Ambil nama file dari URI, polling status file tiap ±5 detik sampai `ACTIVE` (`FAILED` = gagal), unduh, lalu simpan ke storage.

Catatan perilaku: `GET /interactions/{id}` mengembalikan video sebagai base64 inline meskipun awalnya `delivery: uri`; field `uri` hanya dijamin ada di respons pembuatan awal.

### 7.4 Contoh kerangka PHP (di dalam `GeminiOmniProvider`)

```php
$payload = [
    'model' => 'gemini-omni-1.1-flash',
    'input' => $dto->imageBase64
        ? [
            ['type' => 'image', 'data' => $dto->imageBase64, 'mime_type' => $dto->imageMime],
            ['type' => 'text', 'text' => $dto->finalPrompt],
        ]
        : $dto->finalPrompt,
    'response_format' => [
        'aspect_ratio' => $dto->aspectRatio->value,   // "9:16" | "16:9"
        'resolution'   => $dto->resolution->value,    // "360p" | "720p" | "1080p"
        'delivery'     => $dto->resolution->needsUriDelivery() ? 'uri' : 'inline',
    ],
];

$response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
    ->timeout(300)
    ->retry(3, 2000, fn ($e) => $e instanceof RequestException && $e->response->serverError())
    ->post('https://generativelanguage.googleapis.com/v1beta/interactions', $payload)
    ->throw();

// Inline: base64_decode(data dari output video)
// URI   : polling status file sampai ACTIVE, lalu unduh
```

Nama field respons dan detail parameter (mis. `delivery: inline`) perlu diverifikasi terhadap dokumentasi saat Fase 0.

### 7.5 Batasan API yang memengaruhi produk

| Batasan                                                                                                                    | Dampak pada aplikasi                                                           |
| -------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Safety filter pada input dan output (bervariasi per wilayah)                                                               | Tampilkan pesan khusus saat diblokir; jangan otomatis retry                    |
| Bahasa Inggris didukung penuh, bahasa lain belum dievaluasi                                                                | Beri petunjuk di UI; pertimbangkan terjemahan otomatis prompt (P2)             |
| Default model membuat beberapa shot                                                                                        | Toggle "satu adegan" (F6)                                                      |
| Audio dibuat otomatis                                                                                                      | Kontrol audio (F7)                                                             |
| Tidak mendukung system instruction, temperature, top_p, stop sequence, negative prompt                                     | Negatif ditulis di dalam prompt; jangan sediakan slider parameter tersebut     |
| Gambar berisi orang tertentu yang dikenali tidak didukung; gambar berisi anak di bawah umur tidak didukung di EEA/Swiss/UK | Peringatan di UI, tangani penolakan dari API                                   |
| Referensi audio belum didukung                                                                                             | Tidak ada unggah audio                                                         |
| Provisioned throughput tidak didukung                                                                                      | Kapasitas dibatasi kuota proyek API; pakai antrean dan batas konkurensi worker |
| Semua video memuat watermark SynthID (tak terlihat)                                                                        | Tidak ada fitur hapus watermark; tampilkan keterangan "dibuat dengan AI"       |
| `store=false` membuat video tak bisa diedit dengan `previous_interaction_id`                                               | MVP boleh `store=false`; ubah ke `true` saat F13 dibangun                      |

## 8. Arsitektur

```
Browser (Inertia + Vue 3)
   |  POST /videos (multipart)      GET /videos/{id} (halaman)   GET /videos/{id}/status (polling JSON)
   v
Laravel
   |- Middleware: throttle, (auth P1), CSRF
   |- Controller (tipis) <- FormRequest (validasi)
   |      '- VideoService::create(CreateVideoData)
   |            |- PromptBuilderService  -> menyusun final_prompt
   |            |- VideoStorageService   -> simpan gambar unggahan
   |            |- Model Video           -> simpan job (status queued)
   |            '- dispatch(GenerateVideoJob)
   v
Queue worker (Redis/database, dikelola Horizon/supervisor)
   '- GenerateVideoJob -> VideoGenerationService::process(Video)
         |- VideoProviderInterface (GeminiOmniProvider) -> Interactions API
         |- jalur inline: decode base64 | jalur URI: poll file -> unduh
         |- VideoStorageService -> simpan MP4 (local/S3)
         '- update status: done / failed (+ error_code)
   v
DB (MySQL/PostgreSQL)     Storage (Storage facade: video + gambar)
```

### 8.1 Service pattern

Prinsip: controller dan job hanya mengoordinasi, logika bisnis ada di service, dan integrasi eksternal ada di balik interface sehingga mudah diganti dan dites.

| Layer        | Kelas                                                                     | Tanggung jawab                                                                                   |
| ------------ | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Controller   | `VideoController`                                                         | Terima request, panggil service, kembalikan `Inertia::render` atau JSON. Tanpa logika bisnis.    |
| Form Request | `StoreVideoRequest`                                                       | Validasi mode, prompt, gambar (MIME, ukuran), rasio, resolusi, toggle.                           |
| DTO          | `CreateVideoData`, `GenerationRequestData`, `GenerationResultData`        | Objek data bertipe antar layer.                                                                  |
| Enum         | `VideoMode`, `AspectRatio`, `Resolution`, `VideoStatus`, `VideoErrorCode` | Nilai tetap. `Resolution::P360/P720/P1080`, dengan method `needsUriDelivery()` dan label UI.     |
| Service      | `VideoService`                                                            | Use case utama: buat job, ambil status, hapus, coba lagi.                                        |
| Service      | `VideoGenerationService`                                                  | Orkestrasi proses generate: panggil provider, tangani dua jalur hasil, simpan file, ubah status. |
| Service      | `PromptBuilderService`                                                    | Menyusun `final_prompt`: tag `<FIRST_FRAME>` dan kalimat toggle (10.3).                          |
| Service      | `VideoStorageService`                                                     | Simpan/baca/hapus gambar dan video lewat `Storage`, termasuk retensi.                            |
| Contract     | `VideoProviderInterface`                                                  | `generate(GenerationRequestData): GenerationResultData`. Diikat di `AppServiceProvider`.         |
| Implementasi | `GeminiOmniProvider`                                                      | Satu-satunya kelas yang tahu format API Google, header, retry, dan pemetaan error.               |
| Job          | `GenerateVideoJob`                                                        | Ambil `Video`, panggil `VideoGenerationService`. `$timeout` ≈ 330 dtk, `$tries`, `backoff()`.    |
| Model        | `Video`                                                                   | Eloquent model, cast enum, accessor URL.                                                         |
| Exception    | `SafetyBlockedException`, `QuotaExceededException`, `UpstreamException`   | Dipetakan ke `VideoErrorCode` di service.                                                        |

Struktur direktori:

```
app/
  Contracts/VideoProviderInterface.php
  DTOs/
  Enums/
  Exceptions/
  Http/Controllers/VideoController.php
  Http/Requests/StoreVideoRequest.php
  Jobs/GenerateVideoJob.php
  Models/Video.php
  Providers/Gemini/GeminiOmniProvider.php
  Services/
    VideoService.php
    VideoGenerationService.php
    PromptBuilderService.php
    VideoStorageService.php
resources/js/
  Pages/Videos/Create.vue, Show.vue, Index.vue
  Components/ModeTabs.vue, ImageUploader.vue, PromptInput.vue,
             AspectPicker.vue, ResolutionPicker.vue, ResultPanel.vue
  Composables/useVideoStatus.js
```

### 8.2 Backend Laravel

- Konfigurasi lewat `.env` dan `config/services.php` (`GEMINI_API_KEY`, `GEMINI_MODEL`, `VIDEO_MAX_CONCURRENT`, `FILESYSTEM_DISK`, retensi hari).
- Queue: Redis (disarankan) atau database; Horizon untuk memantau. Jumlah worker membatasi konkurensi agar tidak melewati kuota API.
- `GenerateVideoJob`: timeout job lebih besar dari timeout HTTP, `WithoutOverlapping` per video, `failed()` mengubah status menjadi `failed`.
- Retry dengan backoff untuk 429/5xx; tidak retry untuk `SAFETY_BLOCKED`.
- Pemulihan job: command terjadwal menandai video `processing` yang macet melewati batas waktu menjadi `failed` atau `queued` ulang.
- Rate limiting dengan `RateLimiter`/middleware `throttle` per IP/akun; CAPTCHA opsional.
- Command terjadwal untuk hapus video dan gambar yang melewati `expires_at`.

### 8.3 Frontend Inertia + Vue 3

- Halaman: `Videos/Create` (buat video), `Videos/Show` (status dan hasil), `Videos/Index` (riwayat, P1).
- `useForm` Inertia untuk unggah multipart dan menampilkan error validasi per field.
- **Mekanisme cek status: polling.** Halaman `Show` memanggil `GET /videos/{id}/status` tiap 3–5 detik (composable `useVideoStatus`, memakai `usePoll` Inertia atau `fetch`) dan berhenti saat status `done`/`failed`. Polling dipilih untuk MVP karena sederhana, stateless, tidak menahan worker PHP, dan status hanya berubah tiga kali. SSE atau WebSocket (Laravel Reverb) dapat dipertimbangkan kemudian; karena logika dikemas di `useVideoStatus`, mekanismenya bisa diganti tanpa mengubah komponen.
- Karena job punya URL sendiri (`/videos/{uuid}`), pengguna bisa pindah halaman atau refresh dan tetap membuka hasilnya. Untuk pengguna anonim, simpan daftar `uuid` di `localStorage` atau session.
- API key tidak pernah ada di frontend; tidak ada masalah CORS karena satu aplikasi.
- Styling: Tailwind CSS, tema gelap/terang, layout mobile-first. Opsi resolusi menampilkan badge "Hemat" (360p) dan "Upscaled" (1080p).

## 9. Spesifikasi Route & API Internal

| Method | Route                     | Fungsi                                                                                                                                                                                                                           |
| ------ | ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/` atau `/videos/create` | Halaman Buat Video (Inertia)                                                                                                                                                                                                     |
| POST   | `/videos`                 | Buat job. Multipart: `mode` (`text`/`image`), `prompt`, `image` (opsional), `aspect_ratio` (`9:16`/`16:9`), `resolution` (`360p`/`720p`/`1080p`), `single_scene`, `no_dialogue`, `background_music`. Redirect ke `/videos/{id}`. |
| GET    | `/videos/{id}`            | Halaman status dan hasil (Inertia)                                                                                                                                                                                               |
| GET    | `/videos/{id}/status`     | JSON untuk polling: `status` (`queued`/`processing`/`done`/`failed`), `video_url`, `error_code`, `error_message`, posisi antrean                                                                                                 |
| GET    | `/videos/{id}/file`       | Streaming/unduh MP4 (signed URL atau id acak)                                                                                                                                                                                    |
| POST   | `/videos/{id}/retry`      | Coba lagi job yang gagal (bukan `SAFETY_BLOCKED`)                                                                                                                                                                                |
| GET    | `/videos`                 | Riwayat (P1)                                                                                                                                                                                                                     |
| DELETE | `/videos/{id}`            | Hapus video (P1)                                                                                                                                                                                                                 |
| GET    | `/up`                     | Health check bawaan Laravel; kesiapan koneksi ke Google dicek terpisah                                                                                                                                                           |
| GET    | `/api/v1/...`             | REST API publik dengan Sanctum (P1)                                                                                                                                                                                              |

**Kode error:** `INVALID_INPUT`, `SAFETY_BLOCKED`, `QUOTA_EXCEEDED`, `RATE_LIMITED`, `UPSTREAM_ERROR`, `TIMEOUT`, `INTERNAL`.

## 10. Model Data & Aturan Prompt

### 10.1 Tabel `videos` (migration Laravel)

| Kolom                                                   | Keterangan                                       |
| ------------------------------------------------------- | ------------------------------------------------ |
| `id` (uuid)                                             | ID job (HasUuids)                                |
| `user_id` (nullable)                                    | Akun (P1)                                        |
| `session_id` (nullable)                                 | Pengenal anonim                                  |
| `mode`                                                  | enum `text` / `image`                            |
| `prompt`                                                | Prompt asli pengguna                             |
| `final_prompt`                                          | Prompt setelah tag/instruksi tambahan            |
| `aspect_ratio`                                          | enum `9:16` / `16:9`                             |
| `resolution`                                            | enum `360p` / `720p` / `1080p`                   |
| `status`                                                | enum `queued` / `processing` / `done` / `failed` |
| `interaction_id`                                        | ID interaksi Google (untuk edit lanjutan)        |
| `input_image_path`, `output_video_path`                 | Lokasi file di storage                           |
| `error_code`, `error_message`                           | Bila gagal                                       |
| `attempts`                                              | Jumlah percobaan                                 |
| `created_at`, `started_at`, `finished_at`, `expires_at` | Waktu dan retensi                                |

### 10.2 Prompt gambar → video

Mode gambar mengirim gambar + teks. `PromptBuilderService` mengawali `final_prompt` dengan `<FIRST_FRAME>`. Sarankan pengguna menulis deskripsi gerakan yang spesifik (kamera, gerak subjek, efek lingkungan); prompt samar seperti "buat bergerak" memberi hasil lebih lemah menurut dokumentasi.

### 10.3 Instruksi tambahan dari toggle

| Toggle       | Kalimat yang ditambahkan ke prompt            |
| ------------ | --------------------------------------------- |
| Satu adegan  | "In a single continuous shot. No scene cuts." |
| Tanpa dialog | "No dialogue."                                |
| Musik latar  | "Include calm background music."              |

## 11. Kebutuhan Non-Fungsional

- **Performa:** request HTTP tidak diblokir oleh generasi video; seluruh pemrosesan berat lewat queue.
- **Skalabilitas:** batas worker sesuai kuota; posisi antrean ditampilkan.
- **Keamanan:** API key hanya di `.env`; validasi MIME dan ukuran file di `FormRequest`; throttle per IP/akun; CSRF aktif; id acak (UUID) dan signed URL untuk file; jangan log API key atau isi base64.
- **Privasi:** gambar unggahan hanya untuk proses generate; retensi (mis. 7 hari untuk anonim) dengan hapus otomatis.
- **Ketersediaan:** retry untuk error sementara; timeout jelas; pemulihan job macet; `failed_jobs` dipantau.
- **Observabilitas:** log terstruktur per job (`video_id`), metrik sukses/gagal, durasi, estimasi biaya per resolusi.
- **Kualitas kode:** service dan provider dites dengan `Http::fake()` dan `Storage::fake()`; feature test untuk alur buat job dan polling.
- **Aksesibilitas & responsif:** nyaman di HP.

## 12. Risiko & Catatan Kebijakan

| Risiko                                                                                  | Mitigasi                                                                                                                    |
| --------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Biaya membengkak jika dibuka "gratis, tanpa login, tanpa batas kuota" seperti referensi | Rate limit, kuota harian, CAPTCHA, alarm biaya; default 720p, tawarkan 360p untuk pratinjau; pertimbangkan login sejak awal |
| Penyalahgunaan konten (deepfake, konten terlarang)                                      | Patuhi kebijakan Google; safety filter API; mekanisme laporan; log audit                                                    |
| Hasil lambat/gagal, upscale 1080p lebih lama                                            | Status jelas, estimasi waktu, retry, jalur URI untuk file besar                                                             |
| Ketergantungan pada satu penyedia                                                       | `VideoProviderInterface` sehingga penyedia bisa diganti tanpa mengubah service                                              |
| Perubahan API (produk baru, nama field bisa berubah)                                    | Pemetaan terpusat di `GeminiOmniProvider`, tes integrasi terjadwal, kunci versi dokumentasi yang dipakai                    |
| Timeout PHP/web server pada request panjang                                             | Semua panggilan Google hanya di queue worker, bukan di request web                                                          |
| Hak cipta gambar unggahan                                                               | Syarat layanan: pengguna menjamin hak atas gambar                                                                           |

## 13. Rencana Rilis

| Fase         | Isi                                                                                                                                       | Estimasi   |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| Fase 0       | Siapkan API key, uji satu panggilan teks dan satu gambar dari `GeminiOmniProvider` (360p, 720p inline, 1080p URI), ukur latensi dan biaya | 1 minggu   |
| Fase 1 (MVP) | F1–F10: skeleton Laravel + Inertia, service layer, queue, storage, UI Vue, polling status, rate limit                                     | 3–4 minggu |
| Fase 2       | Riwayat, login, kuota akun, statistik, tema                                                                                               | 2–3 minggu |
| Fase 3       | Edit percakapan, interpolasi/referensi, REST API publik (Sanctum)                                                                         | 3–4 minggu |
| Fase 4       | Extension, 4K, prompt enhancer, pembayaran                                                                                                | Menyusul   |

## 14. Kriteria Penerimaan MVP

- Video berhasil dibuat dari teks dan dari gambar untuk 9:16 dan 16:9 pada 360p, 720p, dan 1080p (jalur inline dan URI keduanya teruji).
- Job tetap berjalan saat pengguna menutup/pindah halaman, dan hasil dapat dibuka lagi lewat URL job.
- Status render diperbarui otomatis lewat polling tiap 3–5 detik dan berhenti saat status final.
- Video dapat diputar dan diunduh; error safety, kuota, dan timeout tampil dengan pesan yang jelas.
- API key tidak terekspos di frontend maupun log.
- Controller tidak berisi logika bisnis; semua use case lewat service, dan panggilan Google hanya lewat `VideoProviderInterface`.
- Rate limit aktif, konkurensi worker dibatasi, dan biaya harian dapat dipantau.

## 15. Pertanyaan Terbuka

1. Model bisnis: gratis dengan kuota, berlangganan, atau kredit? Berapa biaya per video yang dapat ditanggung?
2. Login dari MVP atau anonim dulu (menentukan desain kuota)?
3. Storage: disk lokal atau S3-compatible? Berapa lama retensi?
4. Perlu dukungan bahasa Indonesia penuh (terjemahan otomatis prompt ke Inggris)?
5. Durasi video hasil generate tidak dinyatakan eksplisit di dokumentasi; perlu diukur saat Fase 0.
6. Driver queue: Redis (disarankan, dengan Horizon) atau database untuk MVP?
