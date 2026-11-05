# Alat deployment

`install-vps.sh` untuk instalasi baru saja, bukan pembaruan VPS yang sudah berjalan.
Path: `/var/www/level-fit/face-attendance`. Skrip menolak folder, vhost atau
service yang sudah tersedia. Tidak mengubah aplikasi utama dan tidak menjalankan
migration/seeder. Konfigurasi database diisi langsung pada editor `.env`.

`schema-vps.sql` dijalankan manual setelah backup, pada database yang sama dengan
koneksi face_enrollment aplikasi utama. Tabel users dan branch_stores harus ada.
Jika masih ada tabel face_demo_* lama, lakukan rename terlebih dahulu agar tidak
membuat tabel kosong paralel. Jangan jalankan installer ulang pada VPS produksi.

Untuk pembaruan beberapa perangkat lihat ../MULTI-DEVICE.md.

Instalasi baru juga memerlukan `2026_11_04_independent_employees.sql` setelah
`schema-vps.sql`. Lihat ../EMPLOYEES.md. Absensi tidak lagi mengimpor users.

Pendaftaran beberapa contoh juga memerlukan `2026_11_04_face_attendance_samples.sql`.
Jalankan setelah skema profil dan Data Pegawai tersedia. Lihat ../FACE-SAMPLES.md.
