# Sistem Antrian Pendaftaran Umum — Klinik/RS

Aplikasi antrean untuk 5 loket pendaftaran umum. Pasien tanpa login ambil nomor
(sekarang: **A-001**, **A-002**, …), petugas loket memanggil manual satu per satu,
layar TV menampilkan nomor yang dipanggil.

Laravel 12 · MySQL/PostgreSQL · Laravel Reverb (WebSocket)

## Cara menjalankan

```bash
composer install
npm install
cp .env.example .env        # lalu isi DB + ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed  # 5 loket
npm run build               # atau: npm run dev
```

`.env` wajib:

```
APP_TIMEZONE=Asia/Jakarta     # tanpa ini "antrean hari ini" nyalahi tanggal
ADMIN_PASSWORD=rahasia        # password halaman /admin
```

Jalankan **3 terminal**:

```bash
php artisan reverb:start   # 1. WebSocket (wajib)
php artisan serve          # 2. Aplikasi
npm run dev                # 3. Aset (opsional kalau sudah npm run build)
```

| Halaman       | URL                       | Untuk siapa                   |
| ------------- | ------------------------- | ----------------------------- |
| Kiosk pasien  | `/antrian`                | Pasien, tanpa login           |
| Display TV    | `/display`                | TV ruang tunggu               |
| Petugas loket | `/petugas/loket/1` … `/5` | Petugas, satu layar per loket |
| Admin         | `/admin`                  | Supervisor                    |

---

## Cara kerja

### Pasien ambil nomor — `takeNumber()`

Nomor urut harian, restart dari `A-001` tiap hari. Nomor disimpan di session
pasien sebagai UUID token (bukan cookie biasa), jadi orang lain tak bisa ikut
mengintip nomornya.

```php
Queue::where('queue_date', $date)->orderByDesc('queue_number')->lockForUpdate()->first();
```

`lockForUpdate()` di dalam transaksi: dua pasien klik bersamaan di detik yang
sama tidak mungkin mendapat nomor sama.

### Petugas panggil — `callNext()`

Ambil antrean `WAITING` paling depan (FIFO), ubah jadi `CALLED`, lalu siarkan
`QueueCalledEvent`.

```php
DB::transaction(function () {
    $next = Queue::where('status', 'WAITING')->orderBy('id')->lockForUpdate()->first();
    $next->update(['status' => 'CALLED', 'counter_id' => $counter->id, 'called_at' => now()]);
});
```

Kunci baris di `WHERE` menggagalkan race: transaksi kedua memblokir di baris
tersebut, membaca ulang setelah transaksi pertama commit, lalu otomatis
mendapat nomor berikutnya.

> **Catatan kegagalan yang sudah pernah terjadi** — `called_at` sempat selalu
> `NULL` karena kolom ada di DB tapi tidak ada di `$fillable` `Queue`. Eloquent
> membuang kolom yang tak terdaftar saat `update()`, tanpa error. Kalau muncul
> symptom "status sudah CALLED tapi jamnya kosong", cek `$fillable` dulu.

### Panggil ulang — `recall()`

Untuk pasien yang belum datang ke loket. Tidak mengubah urutan antrean.

```php
public function recall(int $counterId, int $queueId): JsonResponse
```

Route-nya punya dua parameter (`{counterId}`, `{queue}`) dan Laravel mengisinya
**posisional**. Kalau argumen pertama dinamai `$queueId`, ia menerima `counterId`
bukan id antrian — bug yang sempat muncul karena test lamanya memakai antrian id
1 di loket 1, jadi kedua angka kebetulan sama dan tester tak sadar. Test
`test_recall_uses_queue_id_not_counter_id` kini menjaga ini (pakai loket 2).

### Real-time

`QueueCalledEvent` (channel publik `queue-channel`, event `.queue.called`)
dikirim saat loket memanggil. TV display =

- Listen Echo
- Bunyi bel (Web Audio, disintesis di browser — tak perlu file mp3 di `public/`)
- Judul tab berubah: `A-005 → Loket 2`

> Chrome memblokir audio sebelum ada user gesture — **TV perlu diklik sekali**
> setelah halaman dibuka agar AudioContext aktif.

Tidak pakai TTS: petugas jack nomor manual, lebih jelas di klinik ramai.

### Admin — `/admin`

Satu password dari `.env` (`ADMIN_PASSWORD`), tanpa tabel user.

- **Buat ulang 5 loket** — pengganti `migrate:fresh --seed`
- **Reset antrean hari ini** — hapus baris hari ini saja, nomor kembali `A-001`,
  riwayat hari lain utuh. Menyiarkan `.queue.reset` supaya TV ikut kosong.
- **Aktif/nonaktif loket**

---

## Testing

```bash
php artisan test
```

24 test: FIFO, hak akses recall, guard loket, auth admin, reset, format waktu.

**1 test di-skip** — `QueueConcurrencyTest` butuh MySQL sungguhan karena memakai
`pcntl_fork()` untuk menjalankan 5 proses loket serentak. Lewati-able:

```bash
php artisan test --filter=QueueConcurrencyTest   # dengan DB_CONNECTION=mysql
```

Test itu **tidak** memakai `RefreshDatabase` — trait itu membungkus test dalam
transaksi yang belum commit, sehingga proses anak tidak melihat datanya. Karena
itu test memanggil `artisan migrate:fresh`.

---

## Shared hosting

Pusher (bukan Reverb) — Reverb butuh proses PHP daemon yang tidak tersedia di
shared hosting.

`.env`:

```
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=mt1
```

`resources/js/echo.js` membaca `VITE_BROADCASTER` — isi `pusher` untuk
mengganti broadcaster saat runtime, lalu `npm run build` lagi.

---

## Catatan operasional

- **Reverb harus hidup.** Tanpa itu panggilan loket tak sampai ke TV.
- **Port.** Default Reverb 8080, tapi di mesin ini 8080 dipakai phpMyAdmin —
  set `REVERB_PORT` **dan** `REVERB_SERVER_PORT` ke 8090. Yang controlling bind
  socket adalah `REVERB_SERVER_PORT` (`config/reverb.php:33`).
- **Timezone.** `APP_TIMEZONE` wajib. Server UTC = WIB − 7, jadi jam dipanggil
  tampil 7 jam lebih awal dan `today()` bergeser setelah jam 17:00 WIB.
- **Test race** butuh MySQL/PostgreSQL. Di sqlite, `lockForUpdate()` adalah no-op
  diam-diam (`SQLiteGrammar::compileLock` mengembalikan string kosong).
- **Tidak ada auth untuk petugas loket.** `/petugas/loket/{n}` terbuka untuk
  siapa saja yang tahu URL. Bungkus dengan auth kalau loket bukan area publik.
