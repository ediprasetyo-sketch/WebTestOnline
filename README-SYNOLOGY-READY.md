# WebTestOnline - Synology NAS Ready

Paket ini disiapkan untuk Synology Container Manager tanpa mengubah aplikasi menjadi SQLite.

## Yang sudah disiapkan

- PHP 8.2 + Apache
- PDO MySQL dan ekstensi aplikasi
- `libonig-dev` untuk build `mbstring`
- MariaDB 10.11
- Inisialisasi database otomatis dari `schema.sql` saat volume database masih baru
- Baseline migration marker untuk schema yang sudah lengkap
- Pembuatan akun demo otomatis saat container aplikasi pertama kali berjalan
- Named Docker volume untuk database
- Folder `storage/` terpisah agar data aplikasi tetap bertahan saat image dibangun ulang
- Port LAN dikonfigurasi melalui `APP_PORT`, bukan dikunci ke `127.0.0.1`
- Konfigurasi Apache eksplisit `Require all granted` untuk menghindari 403

## Struktur folder di NAS

Upload seluruh isi branch/release ini ke satu folder, misalnya:

```
/docker/webtestonline/
├── docker-compose.yml
├── .env
├── schema.sql
├── docker/
├── migrations/
├── storage/
├── admin/
├── api/
├── peserta/
└── file aplikasi lainnya
```

Jangan membuat subfolder tambahan seperti `webtestonline/WebTestOnline/...`.

## 1. Siapkan `.env`

Salin `.env.docker.example` menjadi `.env`, lalu isi password dan alamat NAS.

Contoh:

```
APP_PORT=18080
APP_TIMEZONE=Asia/Jakarta
DB_NAME=ujian_online
DB_USER=ujian_app
DB_PASSWORD=PasswordAplikasiKuat123!
DB_ROOT_PASSWORD=PasswordRootBerbeda456!
PUBLIC_BASE_URL=http://192.168.18.47:18080
```

## 2. Synology Container Manager

Buka **Project → Create** dan pilih folder tempat seluruh file di atas berada.

Gunakan file:

```
docker-compose.yml
```

Jalankan project. Container yang diharapkan:

- `webtestonline-db`
- `webtestonline-app`

## 3. Akses aplikasi

Buka:

```
http://IP-NAS:APP_PORT/
```

Contoh:

```
http://192.168.18.47:18080/
```

## Akun awal

Saat database baru dibuat, aplikasi menjalankan `seed.php` secara otomatis:

- Admin: `admin` / `admin123`
- Peserta: `peserta` / `peserta123`

Ganti password setelah berhasil login.

## Penting: database baru vs database lama

`schema.sql` hanya dijalankan otomatis ketika named volume `webtestonline_db` belum ada. Setelah database terbentuk, restart atau rebuild aplikasi tidak menghapus data.

Untuk instalasi benar-benar baru, buat Project baru dengan nama berbeda atau hapus named volume `webtestonline_db` yang terkait project tersebut. Jangan menghapus volume jika masih ingin mempertahankan data.

## Update aplikasi berikutnya

1. Backup database terlebih dahulu.
2. Update source aplikasi di folder project.
3. Rebuild container `webtestonline-app`.
4. Database tetap berada di named volume.

Migration baru yang ditambahkan setelah baseline akan ditangani oleh mekanisme `ensure_migrations()` aplikasi ketika dipanggil oleh aplikasi.
