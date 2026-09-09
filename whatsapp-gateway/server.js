const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');
const http = require('http');
const url = require('url');
const pino = require('pino');
const QRCode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');

const PORT = process.env.PORT || 3000;
let latestQR = null;
let connectionStatus = 'OFFLINE';
let globalSock = null;

/**
 * Format phone number to WhatsApp JID (e.g. 0812... -> 62812...@s.whatsapp.net)
 */
function formatToJid(number) {
    if (!number) return null;
    let clean = String(number).replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    }
    return clean + '@s.whatsapp.net';
}

/**
 * Initialize Baileys WhatsApp Connection
 */
async function connectToWhatsApp() {
    try {
        const { version, isLatest } = await fetchLatestBaileysVersion();
        console.log(`\n========================================`);
        console.log(`📡 WhatsApp Gateway v${version.join('.')} (Latest: ${isLatest})`);
        console.log(`⚙️  Menghubungkan ke WhatsApp Multi-Device...`);
        console.log(`========================================\n`);

        const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');

        const sock = makeWASocket({
            auth: state,
            version,
            logger: pino({ level: 'silent' }), // Meminimalkan log sampah
            browser: ['MyStorage Gateway', 'Chrome', '120.0.0.0'],
            syncFullHistory: false,
            connectTimeoutMs: 60000,
            keepAliveIntervalMs: 30000,
            printQRInTerminal: false, // Ditangani manual via qrcode-terminal
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                connectionStatus = 'WAITING_SCAN';
                console.log('\n🚀 QR Code Baru Terdeteksi!');
                console.log('📱 Scan QR Code di bawah ini melalui WhatsApp di HP Anda:\n');
                
                // Cetak QR Code di terminal (sangat berguna untuk SSH di Armbian STB)
                try {
                    qrcodeTerminal.generate(qr, { small: true });
                } catch (err) {
                    console.log('Gagal mencetak QR di terminal:', err.message);
                }

                // Konversi QR string ke Data URL Base64 untuk Dashboard Web Admin
                try {
                    latestQR = await QRCode.toDataURL(qr);
                } catch (e) {
                    latestQR = qr;
                }
            }

            if (connection === 'close') {
                connectionStatus = 'OFFLINE';
                latestQR = null;
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

                console.log(`❌ Koneksi Terputus (Status Code: ${statusCode}). Menyambung ulang: ${shouldReconnect}`);

                if (shouldReconnect) {
                    console.log('⏳ Mencoba menyambung kembali dalam 5 detik...');
                    setTimeout(() => connectToWhatsApp(), 5000);
                } else {
                    console.log('⚠️ Sesi Keluar (Logged Out). Hapus folder auth_info_baileys untuk login ulang.');
                }
            } else if (connection === 'open') {
                connectionStatus = 'ONLINE';
                latestQR = null;
                console.log('\n✅ GATEWAY WHATSAPP LIVE & TERHUBUNG!\n');
            }
        });

        sock.ev.on('creds.update', saveCreds);
        globalSock = sock;
    } catch (error) {
        console.error('❌ Error saat inisialisasi Baileys:', error.message);
        setTimeout(() => connectToWhatsApp(), 5000);
    }
}

/**
 * HTTP Server API (Port 3000)
 */
const server = http.createServer(async (req, res) => {
    const parsedUrl = url.parse(req.url, true);

    // Header CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    res.setHeader('Content-Type', 'application/json');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    // Health check root
    if (parsedUrl.pathname === '/') {
        res.end(JSON.stringify({
            status: 'ok',
            service: 'MyStorage WhatsApp Gateway',
            connection: connectionStatus,
            port: PORT
        }));
        return;
    }

    // Status endpoint (Mendukung /status dan /status-wa)
    if (parsedUrl.pathname === '/status' || parsedUrl.pathname === '/status-wa') {
        res.end(JSON.stringify({
            status: connectionStatus,
            connected: connectionStatus === 'ONLINE',
            qr: latestQR
        }));
        return;
    }

    // Send Message endpoint (Mendukung GET query dan POST body)
    if (parsedUrl.pathname === '/send') {
        let number = parsedUrl.query.number;
        let msg = parsedUrl.query.msg;

        const processSend = async (targetNumber, messageText) => {
            if (!globalSock || connectionStatus !== 'ONLINE') {
                res.writeHead(503);
                res.end(JSON.stringify({
                    status: 'not_ready',
                    message: 'Gateway WhatsApp belum terhubung. Silakan scan QR code terlebih dahulu.'
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
                console.log(`📤 Pesan terkirim ke: ${targetNumber}`);
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

    // 404 Not Found
    res.writeHead(404);
    res.end(JSON.stringify({ status: 'not_found', message: 'Endpoint tidak ditemukan.' }));
});

server.listen(PORT, () => {
    console.log(`🚀 HTTP API Gateway aktif di port ${PORT}`);
    console.log(`👉 Endpoint Status : http://127.0.0.1:${PORT}/status-wa`);
    console.log(`👉 Endpoint Kirim  : http://127.0.0.1:${PORT}/send?number=08xxx&msg=Hello`);
});

// Jalankan koneksi Baileys
connectToWhatsApp();
