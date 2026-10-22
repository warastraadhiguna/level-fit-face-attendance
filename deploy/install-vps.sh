#!/usr/bin/env bash
# Run interactively as root on Ubuntu 22.04. Does not run migrations or seeders.
set -Eeuo pipefail
umask 027
APP=/var/www/level-fit/face-attendance
DOMAIN=attendance.levelfit-id.com
PHP=/usr/bin/php8.4
SERVICE=face-attendance-python
PORT=8011
die() { echo "STOP: $*" >&2; exit 1; }
[[ $EUID == 0 ]] || die 'Jalankan dengan sudo bash.'
for tool in git nginx certbot python3 curl ss runuser nano; do
    command -v "$tool" >/dev/null || die "Perintah belum tersedia: $tool";
done
[[ -x $PHP && -f /usr/local/bin/composer ]] || die 'PHP 8.4 atau Composer belum tersedia.'
[[ -S /run/php/php8.4-fpm.sock ]] || die 'Socket PHP 8.4 FPM belum tersedia.'
nginx -t
free -h
df -h /
getent ahostsv4 "$DOMAIN" | awk '{print $1}' | grep -qx 156.67.219.118 || die 'DNS belum sesuai.'
[[ ! -e /etc/nginx/sites-available/$DOMAIN && ! -e /etc/nginx/sites-enabled/$DOMAIN ]] || die 'Vhost domain sudah ada; jangan ditimpa.'
if grep -RlE 'server_name[^;]*attendance\.levelfit-id\.com' /etc/nginx/sites-enabled/ >/dev/null; then
    die 'Domain sudah dipakai oleh konfigurasi nginx lain.'
fi
[[ ! -e /etc/systemd/system/$SERVICE.service ]] || die 'Unit service sudah ada.'
[[ -z $(ss -H -ltn "sport = :$PORT") ]] || die "Port $PORT sudah dipakai."
[[ ! -e $APP ]] || die "Folder $APP sudah ada; periksa sebelum melanjutkan."
curl --fail --silent --show-error --max-time 20 https://fitbull.id/ -o /dev/null
read -r -p 'Email untuk sertifikat HTTPS: ' CERT_EMAIL </dev/tty
[[ $CERT_EMAIL == *@*.* ]] || die 'Email tidak valid.'

# Never perform apt upgrades or restart shared services.
if ! python3 -m venv /tmp/face-attendance-venv-check-$$; then
    die 'python3-venv belum tersedia. Pasang: NEEDRESTART_MODE=l apt-get install -y python3-venv; lalu jalankan ulang.'
fi
rm -rf "/tmp/face-attendance-venv-check-$$"
mkdir -p "$(dirname "$APP")"
git clone --branch main --single-branch https://github.com/warastraadhiguna/level-fit-face-attendance.git "$APP"
cd "$APP/laravel"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP" /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
chgrp -R www-data "$APP"
cp .env.example .env
python3 - <<'PY'
from pathlib import Path
p = Path('.env')
s = p.read_text()
updates = {
 'APP_ENV': 'production', 'APP_DEBUG': 'false',
 'APP_URL': 'https://attendance.levelfit-id.com',
 'APP_TIMEZONE': 'Asia/Jakarta', 'LOG_LEVEL': 'warning',
 'SESSION_COOKIE': 'levelfit_attendance_session',
 'SESSION_SECURE_COOKIE': 'true', 'SESSION_DOMAIN': 'null',
 'FACE_SERVICE_URL': 'http://127.0.0.1:8011',
 'DB_SOCKET': '/run/mysqld/mysqld.sock',
 'DB_DATABASE': 'ISI_DATABASE_YANG_SAMA_DENGAN_ENROLLMENT',
 'DB_USERNAME': 'ISI_USER_DATABASE', 'DB_PASSWORD': '',
 'FACE_ENROLLMENT_URL': 'https://ISI_DOMAIN_APLIKASI_UTAMA/enroll',
}
lines = s.splitlines()
for key, value in updates.items():
    lines = [line for line in lines if not line.startswith(key + '=')]
    lines.append(key + '=' + value)
p.write_text('\n'.join(lines) + '\n')
PY
chown root:www-data .env
chmod 640 .env
echo 'Isi DB_HOST/PORT/DATABASE/USERNAME/PASSWORD dan FACE_ENROLLMENT_URL pada editor.'
echo 'Password dengan simbol/spasi gunakan tanda kutip tunggal. Simpan Ctrl+O, Enter; keluar Ctrl+X.'
nano .env </dev/tty >/dev/tty
grep -qE 'ISI_DATABASE|ISI_USER|ISI_DOMAIN' .env && die 'Placeholder .env belum diganti.'
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 750 {} +
find storage bootstrap/cache -type f -exec chmod 640 {} +
"$PHP" artisan key:generate --force
runuser -u www-data -- "$PHP" artisan config:cache

echo 'Memeriksa koneksi dan struktur database (tanpa menulis data)...'
runuser -u www-data -- "$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$required = [
 'users' => ['id','full_name','branch_store_id','deleted_at'],
 'branch_stores' => ['id','name','face_enrollment_enabled'],
 'face_attendance_employees' => ['id','source_user_id','face_embedding','face_model'],
 'face_attendance_records' => ['id','employee_id','branch_store_id','attendance_date'],
 'face_attendance_devices' => ['id','branch_store_id','token_hash','pairing_hash','owner_name','owner_login_branch_store_id','owner_login_branch_name','owner_recorded_at'],
];
foreach ($required as $table => $columns) {
    if (!Illuminate\Support\Facades\Schema::hasTable($table)) {
        fwrite(STDERR, "Tabel belum tersedia: $table. Jalankan SQL manual terlebih dahulu.\n"); exit(1);
    }
    foreach ($columns as $column) {
        if (!Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
            fwrite(STDERR, "Kolom belum tersedia: $table.$column. Jalankan SQL manual terlebih dahulu.\n"); exit(1);
        }
    }
}
echo "Database siap. Tidak ada migration/seeder dijalankan.\n";
PHP

cd "$APP/face-service"
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
.venv/bin/python download_models.py
chgrp -R www-data "$APP/face-service"
cat > /etc/systemd/system/face-attendance-python.service <<'UNIT'
[Unit]
Description=Level Fit face recognition (localhost only)
After=network.target
[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/level-fit/face-attendance/face-service
Environment=PYTHONDONTWRITEBYTECODE=1
Environment=OMP_NUM_THREADS=1
Environment=OPENBLAS_NUM_THREADS=1
ExecStart=/var/www/level-fit/face-attendance/face-service/.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8011 --workers 1 --limit-concurrency 4
Restart=on-failure
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
MemoryMax=1G
CPUQuota=100%
UMask=0027
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now "$SERVICE"
curl --fail --silent --show-error --retry 12 --retry-connrefused --retry-delay 2 --max-time 10 http://127.0.0.1:8011/health

cat > "/etc/nginx/sites-available/$DOMAIN" <<'NGINX'
server {
    listen 80;
    server_name attendance.levelfit-id.com;
    root /var/www/level-fit/face-attendance/laravel/public;
    index index.php;
    client_max_body_size 8m;
    access_log /var/log/nginx/face-attendance.access.log;
    error_log /var/log/nginx/face-attendance.error.log;
    add_header X-Content-Type-Options nosniff always;
    add_header Referrer-Policy same-origin always;
    add_header Permissions-Policy "camera=(self), microphone=()" always;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param PHP_VALUE "upload_max_filesize=6M\npost_max_size=8M\nmax_execution_time=60";
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 65s;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -s "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/$DOMAIN"
if ! nginx -t; then
    rm "/etc/nginx/sites-enabled/$DOMAIN"
    die 'Konfigurasi baru gagal validasi; symlink dilepas dan nginx belum di-reload.'
fi
systemctl reload nginx
certbot --nginx --non-interactive --agree-tos --email "$CERT_EMAIL" -d "$DOMAIN" --redirect
nginx -t
curl --fail --silent --show-error --max-time 20 "https://$DOMAIN/activate" -o /dev/null
curl --fail --silent --show-error --max-time 20 https://fitbull.id/ -o /dev/null
systemctl is-active "$SERVICE"
echo "SELESAI: https://$DOMAIN/activate"
echo 'Aplikasi utama tidak diubah. Atur FACE_ATTENDANCE_URL pada .env aplikasi utama bila fitur perangkat sudah siap.'
