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
