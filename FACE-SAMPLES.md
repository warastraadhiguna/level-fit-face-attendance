# Beberapa contoh wajah per pegawai

Staff → Pendaftaran Wajah (Owner/Admin, cabang login, fitur wajah aktif) kini
menambahkan contoh, bukan mengganti enrollment. Panduan 3 contoh: depan, sedikit
kiri, sedikit kanan. Label posisi dipilih operator; sistem tidak mengklaim bahwa
pose telah diukur otomatis. Pegawai dengan 1 contoh lama tetap dapat absen.
Maksimal 5 contoh per profil, dihitung kembali di bawah lock pegawai sehingga
permintaan bersamaan tidak melampaui batas. Tidak menyimpan foto asli pendaftaran.

Operator dapat melihat jumlah/label dan menghapus contoh. Contoh terakhir yang
dihapus membuat pegawai tidak dapat absen sampai enroll lagi. Riwayat absensi
serta foto buktinya tetap tersedia. Hanya metadata contoh dikirim ke browser;
embedding tidak disertakan. Contoh baru yang terlalu berbeda dari contoh lama
(model sama, cosine < 0.363) ditolak; Owner/Admin harus memastikan identitas dan
menghapus contoh lama jika benar-benar perlu pendaftaran ulang.

## Kualitas foto

Python /face/enroll menerima check_quality=true dari aplikasi utama dan wajib
mengembalikan quality_checked=true. Server utama menolak service lama yang belum
mendukungnya. Pemeriksaan dilakukan pada potongan wajah: minimum 80 piksel dan
2,5% luas gambar, wajah tidak terpotong, rata-rata terang 40–220, dan variansi
Laplacian minimal 25 pada crop 160x160. Ini heuristik awal, bukan jaminan kualitas
atau liveness; perlu uji kamera nyata sebelum perubahan ambang. Sudut miring tidak
dipaksakan atau diverifikasi otomatis. Pakaian/background tidak diukur sebagai
kualitas wajah. Foto terlalu kecil, gelap, terang, buram atau terpotong ditolak
dengan petunjuk berbeda. No-face dan multiple-faces memiliki kode berbeda.

Endpoint yang sama tetap dapat mengekstrak foto absensi tanpa check_quality=true;
aturan baru ini diterapkan pada pendaftaran. Threshold pencocokan absensi dan
margin kandidat antarpegawai tidak diturunkan. Setiap pegawai memiliki satu skor
(best sample), kemudian dibandingkan dengan pegawai kedua. Dua contoh pegawai
yang sama tidak menjadi dua identitas ambigu. Jika contoh yang cocok dihapus saat
inferensi, transaksi absensi menolak hasilnya dan meminta foto ulang.

## Database dan deployment manual

1. Backup database bersama. Jalankan deploy/2026_11_04_face_attendance_samples.sql
   sekali sebelum kode baru dipakai. Tabel face_attendance_employees harus sudah
   ada; untuk instalasi baru, jalankan SQL Data Pegawai sebelumnya terlebih dahulu.
2. SQL membuat face_attendance_samples dan menyalin embedding lama menjadi contoh
   slot 1, tanpa menghapus atau mengubah riwayat. Bisa diulang jika tidak ada
   penulisan enrollment paralel. Jangan menjalankan SQL bersamaan dengan operator
   yang sedang menambah/menghapus contoh.
3. Update Python dan restart hanya face-attendance-python; health harus berhasil.
4. Perbarui aplikasi utama dan absensi pada jendela pembaruan yang sama, bersihkan
   cache view dan route sesuai versi PHP masing-masing. Service utama lama masih
   menulis field tunggal; jangan mengoperasikan versi campuran setelah perpindahan.
5. Periksa contoh lama tetap terlihat; tambahkan variasi dan uji pakaian, rambut,
   cahaya, salah orang, serta wajah mirip. Beberapa contoh meningkatkan cakupan,
   tidak menjamin semua perubahan penampilan selalu dikenali.

Migration 2026_11_04_000002_create_face_attendance_samples.php tersedia pada kedua
aplikasi dengan koneksi database masing-masing; cukup jalankan di database bersama
satu kali. Tidak perlu mengeksekusi ulang semua migration historis yang dibuat
manual. Field face_attendance_employees.face_embedding/face_model dipertahankan
sebagai salinan contoh pertama untuk kompatibilitas; sumber recognition adalah
koleksi face_attendance_samples. Rollback tidak menghapus biometrik.
