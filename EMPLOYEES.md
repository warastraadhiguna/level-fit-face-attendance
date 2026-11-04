# Data pegawai absensi terpisah

Menu Staff → Data Pegawai (`/employees`) dikelola Owner/Admin pada cabang login,
dengan pengaturan wajah cabang aktif. Bisa tambah, cari, edit, nonaktifkan, atau
hapus sementara. Kode pegawai unik termasuk pada pegawai yang dihapus sementara;
kode tidak boleh dipakai ulang untuk identitas lain. Cabang selalu dari akun login,
bukan nilai yang dikirim form. Data ini tidak membuat akun login, trainer atau user.

Tabel employees pada koneksi face_enrollment adalah sumber identitas absensi baru.
Face profile tetap di face_attendance_employees, dihubungkan source_employee_id.
source_user_id lama dipertahankan hanya untuk histori. Tidak ada relasi otomatis,
salin data user, atau pemindahan embedding berdasarkan nama/ID yang kebetulan sama.
Riwayat dan foto audit lama tetap tersedia, dengan foreign key profile lama.

## Deployment database manual

1. Backup database bersama yang digunakan absensi dan face_enrollment utama.
2. Jalankan deploy/2026_11_04_independent_employees.sql setelah memilih database tujuan. Skrip membuat tabel profil wajah jika belum
   tersedia dan menambahkan source_employee_id hanya jika belum ada. Jika masih
   memakai tabel face_demo_*, lakukan rename terlebih dahulu. Untuk instalasi
   absensi baru, tabel face_attendance_records dan face_attendance_devices tetap
   perlu disiapkan melalui skema dasar absensi. Tidak perlu mengeksekusi ulang
   migration historis atau menjalankan migrate:fresh. Dua aplikasi menyediakan
   migration yang sama dengan pengecekan tabel/kolom untuk instalasi via artisan;
   cukup terapkan pada satu database bersama, bukan dua database berbeda.
3. Deploy kode utama dan absensi pada jendela pembaruan yang sama. Jangan hanya
   memperbarui salah satu: keduanya harus menggunakan sumber employees baru.
4. Tambah data pegawai dari Staff → Data Pegawai, lalu enroll masing-masing.
   Enrollment user lama tidak memberi hak absensi setelah perubahan sumber data.
5. Uji masuk/pulang menggunakan perangkat yang sudah aktif. Aktivasi perangkat
   tidak perlu diulang; tabel perangkat tidak diubah.

is_active=false atau deleted_at terisi menolak enrollment dan absensi, termasuk
jika pegawai dinonaktifkan ketika foto sedang diproses. Tidak ada sinkronisasi user
melalui db:seed lagi. Jangan menautkan ID user ke source_employee_id secara massal.

SQL tidak menyentuh users, trainers, data wajah lama, atau riwayat absensi.
