# Informasi halaman absensi

Halaman absensi menampilkan informasi operasional: hasil absensi, pegawai,
waktu, shift, keterlambatan, dan foto bukti milik proses yang baru dilakukan.
Jangan menampilkan skor pencocokan, nama model, konfigurasi, atau penjelasan
tentang keterbatasan verifikasi di halaman perangkat. Skor tetap disimpan untuk
audit server, tetapi tidak dikirim pada respons sukses absensi.

Pesan mentah layanan pengenalan wajah tidak diteruskan ke pengguna. Kode error
kualitas foto yang dikenal dipetakan ke petunjuk aman; kegagalan teknis ditulis
ke log dan pengguna menerima pesan singkat. Error HTML/JSON tidak menampilkan
query, path file, kredensial, atau trace sekalipun APP_DEBUG aktif.

Pengaturan aktivasi perangkat, validasi wajah, algoritma, dan aturan absensi
tetap berjalan. Tidak ada migration/SQL baru. Perubahan informasi pada Master
berada pada repositori new-level-fit-master.
