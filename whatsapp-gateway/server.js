const {
    default: makeWASocket,
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
 * Inisialisasi Koneksi Baileys dengan Pairing Code
 */
async function connectToWhatsApp(phoneNumber = null) {
    if (phoneNumber) {
        savedPhoneNumber = formatPhoneNumber(phoneNumber);
    }

    try {
        const { version, isLatest } = await fetchLatestBaileysVersion();
        console.log(`\n========================================`);
        console.log(`📡 WhatsApp Gateway v${version.join('.')} (Latest: ${isLatest})`);
        console.log(`⚙️  Metode Otentikasi: Pairing Code (Tanpa QR Code)`);
        console.log(`========================================\n`);

        const authPath = path.join(__dirname, 'auth_info_baileys');
        const { state, saveCreds } = await useMultiFileAuthState(authPath);

        const sock = makeWASocket({
            auth: state,
            version,
            logger: pino({ level: 'silent' }), // Heningkan log internal baileys
            browser: ["Chrome (Linux)", "Chrome", "120.0.0.0"],
            syncFullHistory: false,
            connectTimeoutMs: 60000,
            keepAliveIntervalMs: 30000,
            printQRInTerminal: false, // Tidak menggunakan QR Code sama sekali
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect } = update;

            if (connection === 'close') {
                connectionStatus = 'OFFLINE';
                latestPairingCode = null;
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

                console.log(`❌ Koneksi Terputus (Status Code: ${statusCode}). Menyambung ulang: ${shouldReconnect}`);

                if (shouldReconnect) {
                    setTimeout(() => connectToWhatsApp(savedPhoneNumber), 5000);
                } else {
                    console.log('⚠️ Sesi Keluar (Logged Out). Siap untuk pairing baru.');
                }
            } else if (connection === 'open') {
                connectionStatus = 'ONLINE';
                latestPairingCode = null;
                console.log('\n✅ GATEWAY WHATSAPP LIVE & TERHUBUNG DENGAN PAIRING CODE!\n');
            }
        });

        sock.ev.on('creds.update', saveCreds);
        globalSock = sock;

        // Jika nomor sudah ada dan belum tersambung, request pairing code otomatis
        if (!sock.authState.creds.registered && savedPhoneNumber) {
            setTimeout(async () => {
                try {
                    const code = await sock.requestPairingCode(savedPhoneNumber);
                    latestPairingCode = code;
                    connectionStatus = 'WAITING_PAIR';
                    console.log(`\n🔑 KODE PAIRING WHATSAPP: ${code}`);
                    console.log(`👉 Masukkan kode 8 digit di atas pada menu Perangkat Tertaut di WhatsApp HP Anda.\n`);
                } catch (err) {
                    console.error('Gagal generate pairing code otomatis:', err.message);
                }
            }, 3000);
        } else if (!sock.authState.creds.registered) {
            connectionStatus = 'WAITING_PAIR';
        }
    } catch (error) {
        console.error('❌ Error inisialisasi Baileys:', error.message);
        setTimeout(() => connectToWhatsApp(savedPhoneNumber), 5000);
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
        const handlePairingRequest = async (rawPhone) => {
            const phone = formatPhoneNumber(rawPhone);
            if (!phone) {
                res.writeHead(400);
                res.end(JSON.stringify({
                    status: 'error',
                    message: 'Nomor WhatsApp wajib diisi (contoh: 081234567890).'
                }));
                return;
            }

            savedPhoneNumber = phone;

            if (connectionStatus === 'ONLINE') {
                res.end(JSON.stringify({
                    status: 'already_connected',
                    message: 'WhatsApp sudah terhubung.',
                    pairing_code: null
                }));
                return;
            }

            try {
                // Bersihkan sesi lama jika ada tapi tidak aktif
                if (!globalSock || !globalSock.authState?.creds?.registered) {
                    const authPath = path.join(__dirname, 'auth_info_baileys');
                    if (fs.existsSync(authPath)) {
                        fs.rmSync(authPath, { recursive: true, force: true });
                    }
                    await connectToWhatsApp(phone);
                    // Tunggu inisialisasi socket Baileys
                    await new Promise(r => setTimeout(r, 2500));
                }

                if (globalSock) {
                    const code = await globalSock.requestPairingCode(phone);
                    latestPairingCode = code;
                    connectionStatus = 'WAITING_PAIR';
                    console.log(`\n🔑 KODE PAIRING BARU UNTUK ${phone}: ${code}\n`);

                    res.end(JSON.stringify({
                        status: 'success',
                        pairing_code: code,
                        phone: phone,
                        message: 'Kode pairing berhasil dibuat.'
                    }));
                } else {
                    res.writeHead(500);
                    res.end(JSON.stringify({
                        status: 'error',
                        message: 'Gagal menginisialisasi socket WhatsApp.'
                    }));
                }
            } catch (err) {
                console.error('❌ Gagal request pairing code:', err.message);
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
                    handlePairingRequest(parsed.number || parsedUrl.query.number);
                } catch {
                    handlePairingRequest(parsedUrl.query.number);
                }
            });
        } else {
            handlePairingRequest(parsedUrl.query.number);
        }
        return;
    }

    // Reset Session / Logout endpoint
    if (parsedUrl.pathname === '/reset-session' || parsedUrl.pathname === '/logout') {
        try {
            console.log('🔄 Mereset sesi WhatsApp dari Dashboard...');
            if (globalSock) {
                try { await globalSock.logout(); } catch (_) {}
                try { globalSock.end(undefined); } catch (_) {}
            }
            globalSock = null;
            connectionStatus = 'OFFLINE';
            latestPairingCode = null;
            savedPhoneNumber = null;

            const authPath = path.join(__dirname, 'auth_info_baileys');
            if (fs.existsSync(authPath)) {
                fs.rmSync(authPath, { recursive: true, force: true });
                console.log('🗑️  Folder sesi auth_info_baileys dibersihkan.');
            }

            setTimeout(() => connectToWhatsApp(), 1500);

            res.end(JSON.stringify({
                status: 'success',
                message: 'Sesi WhatsApp berhasil di-reset. Siap meminta kode pairing baru.'
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
    console.log(`👉 Endpoint Status  : http://127.0.0.1:${PORT}/status-wa`);
    console.log(`👉 Endpoint Pairing : http://127.0.0.1:${PORT}/pairing-code?number=08xxx`);
});

connectToWhatsApp();
