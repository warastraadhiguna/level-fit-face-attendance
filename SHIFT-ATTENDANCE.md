# Shift dan absensi pegawai

Pengaturan dilakukan di Master melalui Staff: Template Shift (`/employee-shifts`),
Jadwal Pegawai (`/employee-schedules`), Izin & Cuti (`/employee-leaves`), dan
Rekap Kehadiran (`/employee-attendance`). Face Attendance tetap memakai aktivasi
perangkat dan pengenalan wajah yang sudah ada; tidak ada pemilihan identitas dari browser.

## Pemasangan

Deploy perubahan Master **dan** repositori face-attendance. Keduanya harus menunjuk
database FACE yang sama. Jalankan satu kali SQL
`deploy/2026_11_14_employee_attendance_management.sql` (di repositori face-attendance) pada database tersebut,
atau migration baru dari Master:

```sh
php artisan migrate --path=database/migrations/2026_11_14_000001_create_employee_attendance_management.php --force
php artisan optimize:clear
```

Pada aplikasi `face-attendance/laravel`, jalankan `php artisan migrate --force`
dan `php artisan optimize:clear`. Migration kedua aplikasi idempotent: tabel/kolom
yang telah dibuat SQL atau aplikasi lain dilewati. Jangan mengulang ALTER mentah.
Migration Master memakai koneksi `face_enrollment`, bukan koneksi database default.
Tidak ada perubahan atau pemindahan profil wajah dan foto audit.

Samakan `ATTENDANCE_TIMEZONE=Asia/Jakarta` pada kedua aplikasi (default WIB).
Gunakan zona waktu cabang yang sama untuk seluruh perangkat dan penjadwalan;
belum ada zona waktu berbeda per cabang. Sebaiknya samakan `APP_TIMEZONE` juga.
Data historis tanpa jadwal tetap tampil dengan nilai keterlambatan nol;
tidak ada perhitungan ulang atau rekonstruksi jadwal lama.

## Pemakaian

1. Buat template Pagi 06:00–13:00 dan Siang 13:00–19:00. Tentukan toleransi menit.
   Jam akhir lebih kecil dari jam mulai berarti shift berakhir esok hari.
2. Pilih satu/beberapa pegawai, tanggal awal/akhir (maksimal 93 hari), hari dalam
   minggu, dan template. Bisa membuat jadwal Libur tanpa template.
3. Satu pegawai memiliki maksimal satu jadwal per tanggal kerja, lintas cabang.
   Bulk bersifat atomik: konflik satu pegawai/tanggal membatalkan seluruh kiriman.
   Jadwal malam tidak boleh tumpang tindih dengan shift hari berikutnya.
4. Jadwal menyimpan nama/jam/toleransi template saat dibuat. Edit/nonaktifkan template
   tidak mengubah jadwal lama. Untuk mengganti jadwal, hapus lalu buat ulang.
   Jadwal yang sudah memiliki absensi dikunci; template yang dipakai tidak bisa
   dihapus, tetapi bisa dinonaktifkan. Pegawai mobile dapat dijadwalkan dari cabang
   mana pun dan tetap boleh absen pada perangkat cabang lain.
5. Pegawai selfie untuk masuk/pulang. Waktu dari server. Absen masuk tersedia
   mulai 4 jam sebelum shift hingga akhir shift. Setelah toleransi, menit
   terlambat = floor(masuk - mulai - toleransi), minimum nol.
   Pulang awal = floor(akhir shift - pulang), minimum nol.
6. Pulang dipasangkan dengan absen masuk yang belum ditutup dalam 36 jam terakhir,
   termasuk lintas tengah malam/cabang untuk pegawai mobile. Tanggal kerja mengikuti
   absen masuk. Masuk baru ditolak jika masih ada sesi terbuka dalam batas ini.
   Sesi lama yang tak ditutup tetap terlihat sebagai Belum pulang pada rekap.
7. Tanpa jadwal, absensi lama tetap diizinkan dan ditandai Tanpa jadwal. Tidak ada
   keterlambatan/mangkir otomatis untuk hari tanpa jadwal. Hari libur terjadwal
   dan izin/cuti/sakit disetujui menolak absen masuk. Pending tidak memblokir.
8. Petugas mencatat izin/cuti/sakit atas nama pegawai; pengajuan menunggu persetujuan.
   Pemegang Update dapat menyetujui, menolak, atau membatalkan. Pengajuan disetujui
   hanya dapat dibatalkan. Pengajuan lain yang bertabrakan atau sudah ada absensi
   ditolak. Pegawai dikunci saat perubahan jadwal/persetujuan/absensi untuk mencegah
   hasil yang bertentangan. Cuti belum memakai kuota atau pemotongan saldo.
9. Rekap bisa difilter periode, pegawai, status; tersedia ringkasan dan CSV.
   Tidak hadir dihitung setelah shift berakhir, bukan saat masih menunggu jam masuk.
   Tanggal sebelum mulai kerja/setelah resign tidak dihitung. Pegawai nonaktif atau
   soft-delete tetap disertakan untuk riwayat. Durasi = masuk sampai pulang, termasuk
   istirahat; belum menghitung lembur/payroll. Mobile dengan pasangan masuk/pulang
   lintas cabang ditampilkan utuh pada cabang jadwal atau cabang yang dikunjungi.
   CSV memakai perlindungan formula untuk nama/kode yang diawali karakter spreadsheet.

## Akses

Empat fitur baru dapat diatur terpisah pada `/role-feature-access`:
Template Shift (CRUD), Jadwal Shift (Create/Read/Delete), Izin & Cuti
(Create/Read/Update; Update berarti keputusan pengajuan), Rekap (Read termasuk CSV).
Default Owner, Admin, HRD. Role lain perlu grant eksplisit.
Semua mengikuti switch `face_enrollment_enabled` yang sudah ada di Secret Branch Store;
jika mati, fitur tidak muncul di pengaturan role/menu dan endpoint ditolak.

Tidak ada akun pegawai self-service baru, kuota cuti, koreksi jam manual,
atau pemrosesan payroll pada perubahan ini. Kamera/perangkat asli tetap perlu
dicoba setelah deployment; tes otomatis menggunakan foto fixture dan service wajah tiruan.
