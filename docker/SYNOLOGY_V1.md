# Ujian Online — Synology Docker V1

Target: Synology Container Manager, PHP 8.2 + Apache, MariaDB 10.11.

## 1. Prepare folders

Create a project folder on the NAS, for example:

`/volume1/docker/ujian-online`

Clone or copy this repository into that folder.

## 2. Create environment file

Copy `.env.docker.example` to `.env` and replace the placeholder passwords.

For the first LAN test, use:

`PUBLIC_BASE_URL=http://NAS-IP:18080`

Do not commit the real `.env` file.

## 3. Build and start

From the project folder:

`docker compose up -d --build`

Check:

`docker compose ps`

The application container listens on NAS localhost port `18080`; it is intentionally not published directly to the Internet.

## 4. First test

Open from a computer on the same LAN:

`http://NAS-IP:18080`

Login and verify Dashboard Admin, Kelola Soal, Peserta, Ujian, Hasil Ujian, timer, upload gambar, and update system.

## 5. Database migration

For the production move, do not start with an empty database. Export the current XAMPP MariaDB database first, keep the backup untouched, then import it into the Docker MariaDB container.

The application also has migration files and can create missing schema migrations, but this is not a replacement for restoring the existing production data.

## 6. Public access

After the LAN test succeeds, configure Synology Reverse Proxy to forward an HTTPS hostname to `http://127.0.0.1:18080`.

Use a domain/DDNS hostname and a valid TLS certificate. Do not expose MariaDB or the Docker API to the Internet.

## 7. Backup

Back up the MariaDB volume and the `storage` directory. Keep database backups outside the application container.
