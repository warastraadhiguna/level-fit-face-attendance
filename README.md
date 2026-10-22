# Face Attendance — MySQL level-fit

> Pembaruan: satu cabang kini dapat memiliki beberapa perangkat aktif. Lihat [MULTI-DEVICE.md](MULTI-DEVICE.md) untuk SQL manual dan deployment. Penjelasan satu perangkat pada bagian historis di bawah sudah digantikan.

Laravel 12 + Blade + Bootstrap 5 + FastAPI/OpenCV. Absensi memakai pengenalan wajah 1:N tanpa memilih nama. Semua file aplikasi Face Attendance berada dalam folder ini.

## Database yang digunakan

Runtime memakai **MySQL `level-fit`**, bukan SQLite. Data pegawai bersumber dari akun aktif `users` (`deleted_at IS NULL`), dengan nama cabang dari `branch_stores`. Sinkronisasi awal menghasilkan 51 pegawai.

Tabel fitur Face Attendance:

- `face_attendance_employees`: profil pegawai, `source_user_id`, embedding dan model wajah. Kode pegawai `USER-{id users}`.
- `face_attendance_records`: riwayat, waktu server, score, distance, dan path foto audit.
- `face_attendance_migrations`: catatan migrasi khusus aplikasi Face Attendance.

Tabel sumber `users` dan `branch_stores` hanya dibaca. Password, email, dan token login tidak disalin. Jalankan `php artisan db:seed` untuk memperbarui nama/role/cabang dan menambah akun aktif baru. Sinkronisasi berulang mempertahankan enrollment dan histori yang sudah ada. Daftar ini merupakan hasil sinkronisasi, bukan sinkronisasi otomatis setiap request. Pegawai yang sudah diimpor tetap tersimpan untuk mempertahankan history jika akun sumber kemudian dihapus.

SQLite demo beserta enrollment, absensi dan foto audit lama telah dibersihkan. Setiap pegawai perlu enroll wajah kembali.

## Struktur penting

```text
face-attendance/
├── laravel/
│   ├── app/Http/Controllers/AttendanceController.php
│   ├── app/Models/{Employee,Attendance}.php
│   ├── app/Services/{FaceVerificationService,FaceRecognitionService}.php
│   ├── config/{database,face}.php
│   ├── database/migrations/2026_10_14_000000_create_demo_tables.php
│   ├── database/seeders/DatabaseSeeder.php
│   ├── resources/views/attendance.blade.php
│   ├── public/assets/{bootstrap.min.css,attendance.css,camera.js}
│   └── tests/Feature/{AttendanceTest,EmployeeSyncTest}.php
├── face-service/
│   ├── app/main.py
│   ├── download_models.py
│   ├── models/
│   ├── requirements.txt
│   └── test_service.py
└── tools/ (alat validasi lokal)
```

## Windows / PowerShell

Prasyarat: MySQL dengan database `level-fit`, Composer 2, PHP 8.2+, Python 3.11–3.14 64-bit, browser modern, dan webcam. PHP membutuhkan `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`, `dom`, `xml`, `ctype`, dan `tokenizer`. Untuk test, aktifkan `pdo_sqlite` juga.

PHP yang berhasil diuji tersedia di `C:\wamp64\bin\php\php8.2.28\php.exe`. Di PowerShell, aktifkan untuk sesi terminal:

```powershell
$env:Path = "C:\wamp64\bin\php\php8.2.28;" + $env:Path
php -v
```

Pastikan MySQL berjalan. Jangan memakai PHP 5.6/7.4 untuk aplikasi Face Attendance ini.

### Terminal 1 — Python

Dari folder `face-attendance`:

```powershell
cd face-service
python -m venv --clear .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python download_models.py
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Bila aktivasi PowerShell ditolak, gunakan `.venv\Scripts\python.exe -m pip install -r requirements.txt`, lalu `.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001`.

Model YuNet (~0.23 MB) dan SFace (~37 MB) diunduh pertama kali dari OpenCV Zoo; file yang checksum-nya valid tidak diunduh ulang. Script memverifikasi SHA256 yang dipatok. Endpoint `/health` memuat model dan mengembalikan 503 jika model tidak tersedia.

### Terminal 2 — Laravel

Dari folder `face-attendance`:

```powershell
cd laravel
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
# Untuk instalasi baru saja; .env yang tersedia sudah memiliki APP_KEY.
# php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan storage:link --force
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Isi koneksi lokal yang benar pada `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=level-fit
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
CACHE_STORE=file
APP_TIMEZONE=Asia/Bangkok
FACE_SERVICE_URL=http://127.0.0.1:8001
FACE_SERVICE_TIMEOUT=30
FACE_RECOGNITION_THRESHOLD=0.45
FACE_RECOGNITION_MARGIN=0.08
```

Password pada `.env.example` sengaja kosong; `.env` lokal telah memakai kredensial MySQL yang tersedia. Setelah mengubah `.env`, jalankan `php artisan config:clear`. Untuk WIB gunakan `APP_TIMEZONE=Asia/Jakarta`.

**Jangan menjalankan `migrate:fresh`, `db:wipe`, atau reset seluruh database `level-fit`**: database ini juga berisi tabel aplikasi utama. Migrasi demo hanya membuat tabel `face_attendance_*` dan memakai repository migrasinya sendiri.

Foto audit memakai Laravel Storage pada `storage/app/private/attendance/YYYY/MM/DD/`. Foto diakses melalui route `/attendance/photos/{id}` yang memeriksa perangkat/cabang, bukan lewat public storage.

## URL dan alur

- Absensi: **http://localhost:8000/**
- Enrollment: **http://localhost:8000/enroll**
- Health model: **http://127.0.0.1:8001/health**

Gunakan localhost agar izin kamera browser berfungsi. Enrollment: pilih nama akun pegawai yang benar → izinkan kamera → Ambil Foto → Daftarkan Wajah. Absensi: langsung Ambil Foto → ABSEN MASUK/PULANG. Nama terdeteksi ditampilkan pada kartu sukses dan riwayat. Jangan mendaftarkan wajah yang sama atas dua nama.

Browser memanggil Laravel. `FaceVerificationService` mengirim foto ke Python untuk ekstraksi embedding nyata melalui `/face/enroll`; Python tidak menyimpan data dan tidak menerima identitas pegawai. `FaceRecognitionService` membandingkan embedding selfie dengan seluruh embedding terdaftar. Identitas terbaik harus mencapai threshold 0.45 dan selisih minimal 0.08 dari kandidat kedua. Identitas tidak dikenal atau ambigu ditolak tanpa menyimpan absensi/foto audit. ID pegawai dari browser tidak menentukan identitas absensi.

Threshold recognition berada di environment **Laravel**. Endpoint Python `/face/verify` 1:1 tetap tersedia dengan threshold terpisah `FACE_MATCH_THRESHOLD` (default 0.363). Environment Python dibaca dari proses, bukan otomatis dari file `.env`:

```powershell
$env:FACE_MATCH_THRESHOLD = "0.363"
$env:FACE_DETECTION_THRESHOLD = "0.9"
```

Score adalah cosine similarity [-1,1], bukan persentase keyakinan. Distance adalah Euclidean distance embedding ternormalisasi. Threshold awal demo perlu dikalibrasi memakai wajah nyata.

Model [YuNet dan SFace](https://docs.opencv.org/4.x/d0/dd4/tutorial_dnn_face.html) dipilih karena CPU friendly, menyediakan landmark/alignment dan embedding ONNX melalui OpenCV, tanpa training atau TensorFlow/PyTorch. Lisensi model disimpan di `face-service/models/licenses/`.

Waktu resmi berasal dari `now()` Laravel. Satu check-in/check-out per pegawai per tanggal; check-out membutuhkan check-in. Transaksi mengunci penulisan pegawai sebelum pemeriksaan harian, dengan unique constraint sebagai pengaman tambahan. Service timeout dan kegagalan koneksi menghasilkan pesan yang dapat ditangani UI.

## Pengujian

```powershell
# Laravel
php artisan test
# Python, di face-service dengan venv aktif
pip install httpx
python -m unittest test_service -v
```

Test Laravel memakai **SQLite in-memory khusus pengujian**, tidak memakai database MySQL asli. Ini tidak membuat kembali file SQLite runtime aplikasi.

Validasi terakhir:

- **14 tests, 47 assertions**, lolos (PHP 8.2.28).
- Migration MySQL level-fit dan sinkronisasi **51 akun aktif** berhasil.
- Tabel `users` tetap memiliki 103 akun termasuk akun yang dihapus; source tidak dimutasi.
- `face_attendance_records`: 0; enrollment wajah: 0 setelah pembersihan.
- Absensi dan enrollment HTTP 200; halaman enrollment menampilkan 51 pegawai.
- Laravel Windows → Python: ekstraksi model asli berhasil.
- Lima test Python model asli sebelumnya lolos; wajah berbeda, dua wajah, tidak ada wajah, dan gambar rusak ditangani.

Checklist manual:

- [ ] Enroll wajah atas nama pegawai yang benar.
- [ ] Selfie pegawai tersebut → identitas benar, check-in sukses.
- [ ] Wajah belum terdaftar → ditolak tanpa menyimpan attendance.
- [ ] Dua wajah dalam capture → ditolak.
- [ ] Wajah sama terdaftar pada dua akun → ditolak sebagai ambigu.
- [ ] Check-out sebelum check-in → ditolak.
- [ ] Double check-in dan double check-out → ditolak.
- [ ] Setelah check-in, check-out → sukses dengan foto audit.
- [ ] Matikan Python → pesan service tidak tersedia.
- [ ] Periksa mobile layout, izin kamera, waktu server, dan riwayat.

## Batasan

Aplikasi absensi memakai aktivasi perangkat tanpa login per pegawai, liveness, anti-spoofing, GPS, geofence, payroll, shift, atau notifikasi. Pemilihan nama saat enrollment menentukan hubungan wajah dengan akun; sistem tidak mengetahui identitas asli tanpa enrollment yang benar. Foto audit disimpan privat; akses foto memerlukan perangkat aktif pada cabang yang sama. UI hanya menerima capture kamera, tetapi server tidak dapat membuktikan asal kamera dari payload HTTP. Cahaya, pose, kualitas capture dan jumlah kandidat memengaruhi hasil. Webcam nyata tetap perlu diuji manual. Model berganti memerlukan enrollment ulang.

**VALID — Face Attendance siap dicoba menggunakan database level-fit.**

## Perubahan nama fitur

Folder aplikasi sekarang `face-attendance/`. Label Demo, nama controller, view, stylesheet, dan konfigurasi JavaScript telah diganti menjadi Face Attendance. Foto tetap di `face-attendance/laravel/storage/app/private/attendance/YYYY/MM/DD/`.

Tabel saat ini: `face_attendance_employees`, `face_attendance_records`, dan `face_attendance_migrations`. Tabel lama `face_demo_*` di-rename melalui migration master `2026_10_15_000002_rename_face_attendance_tables.php`. Data, ID, embedding, foreign key, dan path foto dipertahankan. Nama migration historis tetap disimpan untuk mencatat upgrade, tidak perlu dihapus/diulang.

Untuk upgrade instalasi lama, jalankan migration rename khusus di master **sebelum** menjalankan kode absensi baru. Jangan membuat tabel paralel atau menjalankan migrate:fresh pada database bersama.

Validasi rename: 51 pegawai dan 2 absensi saat rename memiliki hash data identik sebelum dan sesudah perubahan nama tabel. Riwayat baru setelah validasi tetap dapat bertambah.

## Pengaturan pendaftaran wajah per cabang

Enrollment dikelola di master, menu **Staff → Pendaftaran Wajah**, dan hanya tersedia jika **Pendaftaran Wajah Pegawai** di `secret-branch-store` diaktifkan untuk cabang login. Default Nonaktif. URL `/enroll` pada aplikasi absensi hanya mengarahkan pengguna ke master; POST lama ditolak. URL tujuan diatur melalui `FACE_ENROLLMENT_URL` pada `.env`.

## Satu perangkat absensi per cabang — Owner saja

1. Owner login di master, buka **Staff → Perangkat Absensi** (`/attendance-device`). Admin/CS/FC/PT tidak dapat membuka, membuat kode, atau mencabut akses.
2. Isi nama HP, lalu **Buat Kode Aktivasi**. Kode 12 digit hex berlaku **5 menit**, sekali pakai, ditampilkan sekali. Owner memasukkannya sendiri pada browser HP yang dipilih melalui **/activate** di aplikasi absensi.
3. Setelah aktif, pegawai cukup selfie. Tidak ada password yang harus diketik setiap absen. Satu baris perangkat unik per cabang memastikan hanya satu token perangkat aktif.
4. Untuk ganti HP: buat kode baru, pakai pada HP baru. HP lama tetap aktif sampai kode baru dipakai; sesudah itu aksesnya langsung ditolak.
5. **Cabut Akses Perangkat** langsung menonaktifkan token aktif dan semua kode yang masih menunggu.

Database `face_attendance_devices` menyimpan hash SHA256 token/kode, label HP, cabang, Owner penerbit, waktu aktivasi dan kedaluwarsa. Kode/token asli tidak disimpan di database. Cookie perangkat dienkripsi Laravel, HttpOnly, SameSite Strict, host-only, dan Secure melalui HTTPS. Token perangkat berlaku maksimal 365 hari; penghapusan cookie/pergantian browser memerlukan aktivasi ulang. Jangan membagikan kode aktivasi kepada pegawai.

Ini pengikatan ke **browser**, bukan IMEI/identitas fisik HP. Token yang berhasil disalin dapat digunakan oleh pihak lain sampai dicabut. Untuk HP khusus, gunakan mode kiosk/MDM. Penggunaan dari HP memerlukan domain HTTPS yang dapat dijangkau perangkat tersebut; `localhost` di HP tidak menunjuk komputer server. Aktivasi HTTP hanya diperbolehkan di environment local/testing; production memerlukan HTTPS dan konfigurasi trusted proxy yang sesuai bila di belakang proxy.

Middleware memeriksa token pada setiap halaman, kiriman absensi, dan foto. Pengenalan hanya membandingkan pegawai sumber yang aktif, belum soft-delete, dan cabangnya sama dengan perangkat. Cabang dari body request diabaikan. Token diperiksa ulang dengan row lock setelah inferensi sebelum menyimpan sehingga pencabutan saat proses berlangsung tetap menolak absensi. Riwayat dan foto dibatasi per cabang; `branch_store_id` disimpan pada attendance sebagai snapshot.

Foto audit sekarang di `storage/app/private/attendance/YYYY/MM/DD/` (disk local). Foto lama telah dipindahkan dengan verifikasi checksum, lalu salinan publik dihapus. Endpoint foto memerlukan cookie perangkat dan memberi header no-store. Root/POST absensi tanpa perangkat aktif ditolak/dialihkan ke halaman aktivasi.

Deploy: jalankan migration master khusus `2026_10_15_000004_create_face_attendance_devices.php`, kemudian `php artisan migrate` pada aplikasi absensi. Migration menambahkan snapshot cabang untuk history lama tanpa menghapusnya. Jika upgrade deployment lain, jalankan `tools/move_private_audit_photos.php` dengan PHP aplikasi absensi untuk memindahkan foto lama (script tidak mengubah baris attendance). Kedua aplikasi harus terhubung ke database `level-fit` yang sama untuk data perangkat. URL halaman aktivasi pada master bisa diatur dengan `FACE_ATTENDANCE_URL`.

Validasi fitur perangkat: 22 tes aplikasi absensi dan 22 tes master terkait lolos. Mencakup Owner-only, kode hash/sekali pakai/kedaluwarsa, cookie terenkripsi/HttpOnly/Secure, rotasi HP, revoke, penolakan cabang berbeda, akun terhapus, pencabutan saat inferensi, serta akses history/foto privat. Belum ada perangkat nyata yang otomatis diaktifkan; Owner memilih HP melalui alur di atas.

## Snapshot cabang login Owner

Saat Owner membuat kode atau mencabut akses perangkat, server menyimpan `owner_id`, `owner_name`, `owner_login_branch_store_id`, `owner_login_branch_name`, dan `owner_recorded_at` (UTC) pada `face_attendance_devices`. Nilai diambil dari akun/cabang autentikasi, bukan request input. Pencatatan ini adalah **snapshot pengaturan terakhir**, bukan log setiap login Owner atau daftar seluruh peristiwa. Snapshot tetap sama saat kode ditebus di HP dan tidak berubah hanya karena akun Owner berpindah cabang atau nama cabang diganti. Halaman Perangkat Absensi menampilkan identitas dan cabang login Owner pada pengaturan terakhir.

Migration master: `2026_10_15_000005_add_owner_login_context_to_attendance_devices.php`. Aplikasi absensi juga memiliki migration idempotent `2026_10_15_000002_add_owner_login_context_to_devices.php`. Data lama yang belum memiliki snapshot dibiarkan kosong, tidak direkonstruksi dari cabang akun saat ini.

## Pegawai dengan nama/wajah sama di dua cabang

Kandidat recognition terlebih dahulu difilter menurut `users.branch_store_id` yang sama dengan cabang perangkat aktif dan `deleted_at IS NULL`. Nama bukan kunci pencocokan identitas; akun dipisahkan melalui `source_user_id`.

Contoh Mila Candisari dan Mila Supriyadi: HP Candisari hanya memilih akun Mila Candisari, HP Supriyadi hanya akun Mila Supriyadi. Embedding dari cabang lain tidak ikut ranking dan tidak menyebabkan hasil ambigu. Cabang/employee_id yang dikirim browser tidak bisa mengganti cabang perangkat. Jika wajah sama terdaftar pada dua akun dalam cabang yang sama, hasil tetap ditolak sebagai ambigu sampai data/enrollment diperbaiki.

Skenario nama dan embedding identik lintas dua cabang telah diuji melalui endpoint absensi dengan dua token perangkat berbeda, termasuk payload yang sengaja memalsukan employee_id/cabang. Data pengujian berada di database in-memory, tidak mengubah data pegawai asli.
