const makeWASocket = require('@whiskeysockets/baileys').default || require('@whiskeysockets/baileys');
const {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');
const http = require('http');
const url = require('url');
const pino = require('pino');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
let latestPairingCode = null;
let savedPhoneNumber = null;
let connectionStatus = 'OFFLINE';
let globalSock = null;

/**
 * Normalisasi nomor HP ke format digit internasional (contoh: 0812... -> 62812...)
 */
function formatPhoneNumber(number) {
    if (!number) return null;
    let clean = String(number).replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    } else if (clean.startsWith('8')) {
        clean = '62' + clean;
    }
    return clean;
}

/**
 * Format nomor HP ke WhatsApp JID
 */
function formatToJid(number) {
    const clean = formatPhoneNumber(number);
    return clean ? clean + '@s.whatsapp.net' : null;
}

/**
 * Tutup socket lama secara bersih
 */
function closeCurrentSocket() {
    if (globalSock) {
        try {
            globalSock.ev.removeAllListeners();
            globalSock.end(undefined);
        } catch (_) {}
        globalSock = null;
    }
}

/**
 * Hubungkan sesi yang sudah terdaftar sebelumnya (jika creds.json ada)
 */
async function connectExistingSession() {
    const authPath = path.join(__dirname, 'auth_info_baileys');
    const credsPath = path.join(authPath, 'creds.json');

    // Jika belum pernah ditautkan, jangan lakukan inisialisasi socket
    if (!fs.existsSync(credsPath)) {
        console.log('ℹ️ Belum ada sesi WhatsApp tersimpan. Menunggu pairing dari dashboard...');
        connectionStatus = 'OFFLINE';
        return;
    }

    try {
        closeCurrentSocket();

        const { state, saveCreds } = await useMultiFileAuthState(authPath);
        const { version } = await fetchLatestBaileysVersion();

        const sock = makeWASocket({
            auth: state,
            version,
            logger: pino({ level: 'silent' }),
            browser: ["Ubuntu", "Chrome", "20.0.04"],
            syncFullHistory: false,
            connectTimeoutMs: 60000,
            keepAliveIntervalMs: 30000,
            printQRInTerminal: false,
        });

        globalSock = sock;
        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', (update) => {
            const { connection, lastDisconnect } = update;

            if (connection === 'close') {
                connectionStatus = 'OFFLINE';
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const isLoggedOut = statusCode === DisconnectReason.loggedOut;

                console.log(`❌ Sesi Terputus (Status Code: ${statusCode}). Logged out: ${isLoggedOut}`);

                if (!isLoggedOut && sock.authState?.creds?.registered) {
                    console.log('🔄 Mencoba menghubungkan ulang dalam 5 detik...');
                    setTimeout(() => connectExistingSession(), 5000);
                }
            } else if (connection === 'open') {
                connectionStatus = 'ONLINE';
                latestPairingCode = null;
                console.log('\n✅ GATEWAY WHATSAPP LIVE & TERHUBUNG!\n');
            }
        });
    } catch (err) {
        console.error('❌ Gagal menyambung sesi yang ada:', err.message);
        setTimeout(() => connectExistingSession(), 5000);
    }
}

/**
 * Request Kode Pairing Baru (Hanya dipanggil saat Admin menekan tombol di web)
 */
async function requestNewPairingCode(rawPhoneNumber) {
    const phone = formatPhoneNumber(rawPhoneNumber);
    if (!phone) {
        throw new Error('Nomor WhatsApp wajib diisi (contoh: 081234567890).');
    }

    savedPhoneNumber = phone;
    console.log(`\n========================================`);
    console.log(`📡 Memulai Pairing Code untuk nomor: ${phone}`);
    console.log(`========================================`);

    // 1. Tutup socket yang sedang aktif
    closeCurrentSocket();

    // 2. Bersihkan folder auth lama agar tidak ada token lama yang berkonflik
    const authPath = path.join(__dirname, 'auth_info_baileys');
    if (fs.existsSync(authPath)) {
        try {
            fs.rmSync(authPath, { recursive: true, force: true });
            console.log('🗑️  Folder sesi lama dibersihkan.');
        } catch (e) {
            console.warn('Gagal menghapus folder sesi lama:', e.message);
        }
    }

    // 3. Buat sesi Baileys baru khusus untuk pairing
    const { state, saveCreds } = await useMultiFileAuthState(authPath);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        auth: state,
        version,
        logger: pino({ level: 'silent' }),
        browser: ["Ubuntu", "Chrome", "20.0.04"],
        syncFullHistory: false,
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 30000,
        printQRInTerminal: false,
    });

    globalSock = sock;
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect } = update;

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const isLoggedOut = statusCode === DisconnectReason.loggedOut;

            console.log(`ℹ️ Status Koneksi Berubah: Tutup (Code: ${statusCode})`);

            // HANYA auto-reconnect jika sudah terdaftar dan bukan logged out
            // JANGAN auto-reconnect saat masih pairing, agar kode tidak hangus!
            if (sock.authState?.creds?.registered && !isLoggedOut) {
                connectionStatus = 'OFFLINE';
                console.log('🔄 Reconnecting sesi terdaftar dalam 5 detik...');
                setTimeout(() => connectExistingSession(), 5000);
            } else {
                connectionStatus = 'OFFLINE';
            }
        } else if (connection === 'open') {
            connectionStatus = 'ONLINE';
            latestPairingCode = null;
            console.log('\n========================================');
            console.log('🎉 WHATSAPP BERHASIL DITAUTKAN DAN LIVE!');
            console.log('========================================\n');
        }
    });

    // 4. Tunggu inisialisasi WebSocket sebelum request pairing code (wajib jeda 2 detik)
    await new Promise((resolve) => setTimeout(resolve, 2000));

    if (!sock.authState.creds.registered) {
        const code = await sock.requestPairingCode(phone);
        latestPairingCode = code;
        connectionStatus = 'WAITING_PAIR';

        console.log(`\n========================================`);
        console.log(`🔑 KODE PAIRING WHATSAPP: ${code}`);
        console.log(`👉 Buka WhatsApp di HP > Perangkat Tertaut > Tautkan dengan nomor telepon saja`);
        console.log(`👉 Masukkan kode 8 digit di atas dalam waktu 60 detik`);
        console.log(`========================================\n`);

        return code;
    } else {
        connectionStatus = 'ONLINE';
        return null;
    }
}

/**
 * Reset Sesi WhatsApp secara penuh
 */
async function resetWhatsAppSession() {
    console.log('🔄 Mereset sesi WhatsApp...');
    closeCurrentSocket();

    connectionStatus = 'OFFLINE';
    latestPairingCode = null;
    savedPhoneNumber = null;

    const authPath = path.join(__dirname, 'auth_info_baileys');
    if (fs.existsSync(authPath)) {
        try {
            fs.rmSync(authPath, { recursive: true, force: true });
            console.log('🗑️  Folder sesi auth_info_baileys berhasil dibersihkan.');
        } catch (e) {
            console.warn('Gagal menghapus folder auth:', e.message);
        }
    }
}

/**
 * Server API HTTP (Port 3000)
 */
const server = http.createServer(async (req, res) => {
    const parsedUrl = url.parse(req.url, true);

    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    res.setHeader('Content-Type', 'application/json');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    // Health check
    if (parsedUrl.pathname === '/') {
        res.end(JSON.stringify({
            status: 'ok',
            service: 'MyStorage WhatsApp Gateway (Pairing Code Mode)',
            connection: connectionStatus,
            pairing_code: latestPairingCode,
            port: PORT
        }));
        return;
    }

    // Status endpoint (Mendukung /status dan /status-wa)
    if (parsedUrl.pathname === '/status' || parsedUrl.pathname === '/status-wa') {
        res.end(JSON.stringify({
            status: connectionStatus,
            connected: connectionStatus === 'ONLINE',
            pairing_code: latestPairingCode,
            phone: savedPhoneNumber,
            qr: null
        }));
        return;
    }

    // Endpoint Permintaan Kode Pairing (/pairing-code)
    if (parsedUrl.pathname === '/pairing-code') {
        const handlePairing = async (rawPhone) => {
            try {
                if (connectionStatus === 'ONLINE') {
                    res.end(JSON.stringify({
                        status: 'already_connected',
                        message: 'WhatsApp sudah terhubung.',
                        pairing_code: null
                    }));
                    return;
                }

                const code = await requestNewPairingCode(rawPhone);
                res.end(JSON.stringify({
                    status: 'success',
                    pairing_code: code,
                    phone: savedPhoneNumber,
                    message: 'Kode pairing berhasil dibuat. Masukkan segera di WhatsApp HP Anda.'
                }));
            } catch (err) {
                console.error('❌ Gagal membuat kode pairing:', err.message);
                res.writeHead(500);
                res.end(JSON.stringify({
                    status: 'error',
                    message: 'Gagal membuat kode pairing: ' + err.message
                }));
            }
        };

        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => { body += chunk.toString(); });
            req.on('end', () => {
                try {
                    const parsed = JSON.parse(body || '{}');
                    handlePairing(parsed.number || parsedUrl.query.number);
                } catch {
                    handlePairing(parsedUrl.query.number);
                }
            });
        } else {
            handlePairing(parsedUrl.query.number);
        }
        return;
    }

    // Reset Session / Logout endpoint
    if (parsedUrl.pathname === '/reset-session' || parsedUrl.pathname === '/logout') {
        try {
            await resetWhatsAppSession();
            res.end(JSON.stringify({
                status: 'success',
                message: 'Sesi WhatsApp berhasil di-reset. Siap untuk meminta kode pairing baru.'
            }));
        } catch (err) {
            console.error('❌ Gagal reset sesi:', err.message);
            res.writeHead(500);
            res.end(JSON.stringify({ status: 'error', message: err.message }));
        }
        return;
    }

    // Send Message endpoint
    if (parsedUrl.pathname === '/send') {
        let number = parsedUrl.query.number;
        let msg = parsedUrl.query.msg;

        const processSend = async (targetNumber, messageText) => {
            if (!globalSock || connectionStatus !== 'ONLINE') {
                res.writeHead(503);
                res.end(JSON.stringify({
                    status: 'not_ready',
                    message: 'Gateway WhatsApp belum terhubung. Silakan hubungkan dengan Kode Pairing terlebih dahulu.'
                }));
                return;
            }

            if (!targetNumber || !messageText) {
                res.writeHead(400);
                res.end(JSON.stringify({
                    status: 'error',
                    message: 'Parameter "number" dan "msg" wajib diisi.'
                }));
                return;
            }

            try {
                const jid = formatToJid(targetNumber);
                await globalSock.sendMessage(jid, { text: messageText });
                console.log(`📤 Pesan WhatsApp terkirim ke: ${targetNumber}`);
                res.end(JSON.stringify({
                    status: 'success',
                    message: `Pesan berhasil dikirim ke ${targetNumber}`
                }));
            } catch (err) {
                console.error(`❌ Gagal kirim pesan ke ${targetNumber}:`, err.message);
                res.writeHead(500);
                res.end(JSON.stringify({
                    status: 'error',
                    message: err.message
                }));
            }
        };

        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => { body += chunk.toString(); });
            req.on('end', () => {
                try {
                    const parsedBody = JSON.parse(body || '{}');
                    processSend(parsedBody.number || number, parsedBody.msg || msg);
                } catch {
                    processSend(number, msg);
                }
            });
        } else {
            processSend(number, msg);
        }
        return;
    }

    res.writeHead(404);
    res.end(JSON.stringify({ status: 'not_found', message: 'Endpoint tidak ditemukan.' }));
});

server.listen(PORT, () => {
    console.log(`🚀 WhatsApp Gateway (Mode Pairing Code) aktif di port ${PORT}`);
    console.log(`👉 Status  : http://127.0.0.1:${PORT}/status-wa`);
    console.log(`👉 Pairing : http://127.0.0.1:${PORT}/pairing-code?number=08xxx`);
});

// Cek apakah ada sesi aktif yang tersimpan untuk langsung dihubungkan
connectExistingSession();