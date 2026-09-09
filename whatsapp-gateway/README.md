# WhatsApp Gateway Multi-Device Service (Port 3000)

Layanan WhatsApp Gateway mandiri menggunakan `@whiskeysockets/baileys` yang terintegrasi langsung dengan MyStorage Cloud dan sistem Radar Sinden.

## Fitur Utama
1. **Multi-Device Support:** Menggunakan `useMultiFileAuthState` untuk menyimpan sesi di folder `auth_info_baileys/`.
2. **Auto Reconnect:** Secara otomatis menyambung kembali jika koneksi internet terputus (kecuali sesi di-logout manual dari HP).
3. **Dual QR Display:**
   - Menampilkan QR Code langsung di **terminal SSH** (via `qrcode-terminal`).
   - Menyediakan format **Base64 Data URL** untuk Dashboard Web Admin MyStorage.
4. **API Endpoint Lengkap:**
   - `GET /status-wa` atau `GET /status` : Mengecek status live dan mengambil data QR.
   - `GET /send?number=08xxx&msg=Halo` atau `POST /send` : Mengirim pesan WhatsApp.

---

## Panduan Instalasi & Menjalankan dengan PM2 di Armbian / Linux

### 1. Masuk ke Folder Gateway
```bash
cd /www/wwwroot/simpan.site/whatsapp-gateway
```

### 2. Instal Dependensi
```bash
npm install
```

### 3. Uji Coba Jalankan Pertama Kali (Untuk Scan QR)
Jalankan sementara menggunakan node:
```bash
node server.js
```
- QR Code akan muncul di terminal.
- Buka aplikasi **WhatsApp** di HP Anda.
- Ketuk **Menu Titik Tiga** (Android) atau **Pengaturan** (iPhone) > **Perangkat Tertaut** > **Tautkan Perangkat**.
- Pindai QR Code di layar terminal.
- Tunggu sampai muncul pesan: `✅ GATEWAY WHATSAPP LIVE & TERHUBUNG!`.
- Tekan `Ctrl + C` untuk keluar.

---

### 4. Menjalankan di Background Menggunakan PM2

#### A. Pastikan PM2 Terpasang
Jika belum memiliki PM2:
```bash
npm install -g pm2
```

#### B. Jalankan Gateway dengan PM2
```bash
pm2 start ecosystem.config.js
```
*Atau secara manual:*
```bash
pm2 start server.js --name "wa-gateway"
```

#### C. Simpan Daftar Proses PM2
Agar PM2 mengingat service ini:
```bash
pm2 save
```

#### D. Aktifkan Auto-Start saat STB / Server Restart (Booting)
```bash
pm2 startup
```
*Salin dan jalankan perintah `sudo env PATH=...` yang muncul di terminal Anda.*

---

## Perintah Penting PM2

- **Melihat Status:** `pm2 status`
- **Melihat Log Realtime:** `pm2 logs wa-gateway`
- **Restart Gateway:** `pm2 restart wa-gateway`
- **Stop Gateway:** `pm2 stop wa-gateway`
- **Hapus Sesi / Reset Login:**
  ```bash
  pm2 stop wa-gateway
  rm -rf auth_info_baileys
  pm2 start wa-gateway --no-daemon
  # Scan QR baru, lalu Ctrl+C dan jalankan normal: pm2 restart wa-gateway
  ```
