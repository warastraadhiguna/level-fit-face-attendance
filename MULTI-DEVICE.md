# Beberapa perangkat absensi per cabang

Owner dapat membuat kode untuk setiap perangkat. Tiap kode membuat baris perangkat
sendiri, berlaku 5 menit dan hanya bisa ditebus sekali. Perangkat yang sudah aktif
tidak kehilangan akses saat perangkat lain diaktifkan. Cabut akses berdasarkan ID
perangkat yang dipilih, selalu dibatasi cabang login Owner.

## SQL manual (database bersama face_enrollment)

Backup database dahulu. Periksa indeks yang tersedia:

```sql
SHOW INDEX FROM face_attendance_devices;
```

Untuk skema awal yang masih memiliki indeks UNIQUE berikut, jalankan sekali:

```sql
ALTER TABLE face_attendance_devices
    ADD INDEX face_attendance_devices_branch_store_id_index (branch_store_id),
    DROP INDEX face_attendance_devices_branch_store_id_unique;
```

Jika indeks biasa sudah ada, cukup DROP indeks UNIQUE. Jika indeks UNIQUE sudah
tidak ada, perubahan skema sudah diterapkan. Semua baris, token aktif, dan kode
tertunda dipertahankan. Indeks UNIQUE token_hash dan pairing_hash tetap dipakai.
Tidak perlu mengulang aktivasi perangkat lama.

Alternatif migration: jalankan hanya migration
2026_10_16_000001_allow_multiple_attendance_devices_per_branch.php pada salah satu
aplikasi. Kedua aplikasi menyediakan migration idempotent untuk database bersama.
Jangan menjalankan seluruh migration lama pada database yang dibuat dengan SQL
manual; riwayat migration-nya belum tentu tercatat.

Deploy perubahan indeks sebelum kode aplikasi utama. Aplikasi absensi mengenali
perangkat berdasarkan token sehingga kode aktivasi yang sudah ada tetap kompatibel.
Satu browser tetap menyimpan satu token; aktivasi kedua pada browser yang sama
mengganti cookie browser tersebut. Gunakan browser/HP berbeda untuk beberapa perangkat.

Menu Perangkat Absensi tetap hanya untuk Owner dengan fitur wajah aktif pada
cabang login. Pembatasan fitur cabang ini hanya mengatur tampilan menu, sesuai
permintaan; akses controller tetap Owner-only.

Daftar perangkat hanya menampilkan token aktif yang belum kedaluwarsa atau kode
aktivasi yang masih berlaku. Perangkat dicabut, token kedaluwarsa, dan kode habis
masa berlaku tidak ditampilkan; tidak perlu menghapus baris database.
