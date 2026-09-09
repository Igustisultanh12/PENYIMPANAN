<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import http from '@/utils/http';
import {
    Server,
    MessageSquare,
    Mail,
    Save,
    RefreshCw,
    Send,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Eye,
    EyeOff,
    ExternalLink,
    Shield,
    QrCode,
    LogOut,
    Smartphone,
    Trash2,
} from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const activeTab = ref<'whatsapp' | 'email'>('whatsapp');
const isLoading = ref(true);
const isSaving = ref(false);
const showPassword = ref(false);

// WhatsApp Form State
const waEnabled = ref('1');
const waGatewayUrl = ref('http://127.0.0.1:3000');
const waStatus = ref<{
    status: string;
    connected?: boolean;
    qr?: string | null;
    raw_qr?: string | null;
    message?: string;
} | null>(null);
const isCheckingWa = ref(false);
const isResettingSession = ref(false);
let waPollTimer: any = null;

// WhatsApp Test State
const waTestPhone = ref('');
const waTestMessage = ref('');
const isSendingWaTest = ref(false);

// SMTP Form State
const mailMailer = ref('smtp');
const mailHost = ref('smtp.gmail.com');
const mailPort = ref('587');
const mailUsername = ref('');
const mailPassword = ref('');
const mailEncryption = ref('tls');
const mailFromAddress = ref('no-reply@simpan.site');
const mailFromName = ref('MyStorage Cloud');

// SMTP Test State
const mailTestEmail = ref('');
const isSendingMailTest = ref(false);

const isConnected = computed(() => {
    return waStatus.value?.status === 'ONLINE' && waStatus.value?.connected !== false;
});

const qrImageSrc = computed(() => {
    const qrVal = waStatus.value?.qr || waStatus.value?.raw_qr;
    if (!qrVal) return null;
    if (qrVal.startsWith('data:image/')) {
        return qrVal;
    }
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=${encodeURIComponent(qrVal)}`;
});

async function loadSettings() {
    isLoading.value = true;
    try {
        const res = await http.get('/admin/settings');
        const data = res.data.data;

        waEnabled.value = data.wa_notifications_enabled ?? '1';
        waGatewayUrl.value = data.wa_gateway_url ?? 'http://127.0.0.1:3000';

        mailMailer.value = data.mail_mailer ?? 'smtp';
        mailHost.value = data.mail_host ?? 'smtp.gmail.com';
        mailPort.value = String(data.mail_port ?? '587');
        mailUsername.value = data.mail_username ?? '';
        mailPassword.value = data.mail_password ?? '';
        mailEncryption.value = data.mail_encryption ?? 'tls';
        mailFromAddress.value = data.mail_from_address ?? 'no-reply@simpan.site';
        mailFromName.value = data.mail_from_name ?? 'MyStorage Cloud';

        if (auth.user?.whatsapp) {
            waTestPhone.value = auth.user.whatsapp;
        }
        if (auth.user?.email) {
            mailTestEmail.value = auth.user.email;
        }
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal memuat pengaturan admin.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function checkWhatsAppStatus(silent = false) {
    if (!silent) isCheckingWa.value = true;
    try {
        const res = await http.get('/admin/whatsapp/status');
        waStatus.value = res.data.data;
    } catch (err: any) {
        waStatus.value = {
            status: 'OFFLINE',
            connected: false,
            message: err.response?.data?.message || 'Tidak dapat menghubungi server backend.',
        };
    } finally {
        if (!silent) isCheckingWa.value = false;
    }
}

async function handleResetSession() {
    if (!confirm('Putus sesi WhatsApp saat ini dan generate QR Code baru untuk scan ulang?')) {
        return;
    }

    isResettingSession.value = true;
    try {
        const res = await http.post('/admin/whatsapp/reset');
        ui.notify(res.data.message || 'Sesi di-reset. Menyiapkan QR Code baru...', 'info');
        waStatus.value = { status: 'WAITING_SCAN', connected: false, qr: null };
        setTimeout(() => checkWhatsAppStatus(false), 2000);
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal mereset sesi WhatsApp.', 'error');
    } finally {
        isResettingSession.value = false;
    }
}

async function saveAllSettings() {
    isSaving.value = true;
    try {
        const payload = {
            wa_notifications_enabled: waEnabled.value,
            wa_gateway_url: waGatewayUrl.value,
            mail_mailer: mailMailer.value,
            mail_host: mailHost.value,
            mail_port: mailPort.value,
            mail_username: mailUsername.value,
            mail_password: mailPassword.value,
            mail_encryption: mailEncryption.value,
            mail_from_address: mailFromAddress.value,
            mail_from_name: mailFromName.value,
        };

        const res = await http.post('/admin/settings', payload);
        ui.notify(res.data.message || 'Pengaturan berhasil disimpan!', 'success');

        // Refresh WhatsApp Status check
        checkWhatsAppStatus(false);
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal menyimpan konfigurasi.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function sendWhatsAppTest() {
    if (!waTestPhone.value) {
        ui.notify('Harap masukkan nomor WhatsApp tujuan pengujian.', 'error');
        return;
    }

    isSendingWaTest.value = true;
    try {
        const res = await http.post('/admin/whatsapp/test', {
            target_phone: waTestPhone.value,
            message: waTestMessage.value || undefined,
        });

        ui.notify(res.data.message || 'Pesan WhatsApp pengujian berhasil terkirim!', 'success');
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal mengirim pesan pengujian WhatsApp.', 'error');
    } finally {
        isSendingWaTest.value = false;
    }
}

async function sendMailTest() {
    if (!mailTestEmail.value) {
        ui.notify('Harap masukkan alamat email tujuan pengujian.', 'error');
        return;
    }

    isSendingMailTest.value = true;
    try {
        const res = await http.post('/admin/mail/test', {
            test_email: mailTestEmail.value,
        });

        ui.notify(res.data.message || 'Email pengujian berhasil dikirim! Silakan periksa inbox/spam.', 'success');
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal mengirim email pengujian. Periksa pengaturan SMTP Anda.', 'error');
    } finally {
        isSendingMailTest.value = false;
    }
}

onMounted(() => {
    loadSettings();
    checkWhatsAppStatus(false);

    // Auto-poll WhatsApp status every 3 seconds while on WhatsApp tab and not yet connected
    waPollTimer = setInterval(() => {
        if (activeTab.value === 'whatsapp' && !isConnected.value) {
            checkWhatsAppStatus(true);
        }
    }, 3000);
});

onUnmounted(() => {
    if (waPollTimer) clearInterval(waPollTimer);
});
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-5xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-600/10 dark:bg-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <Server class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        Admin Gateway & Integrasi
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                            Administrator
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Kelola konfigurasi WhatsApp Gateway internal dan server SMTP pengiriman email sistem
                    </p>
                </div>
            </div>

            <!-- Global Save Button -->
            <button
                @click="saveAllSettings"
                :disabled="isSaving || isLoading"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-md shadow-blue-500/20 transition-all disabled:opacity-50"
            >
                <RefreshCw v-if="isSaving" class="w-3.5 h-3.5 animate-spin" />
                <Save v-else class="w-3.5 h-3.5" />
                <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Pengaturan' }}</span>
            </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 mb-6 border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'whatsapp'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all"
                :class="activeTab === 'whatsapp'
                    ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <MessageSquare class="w-4 h-4" />
                <span>WhatsApp Gateway (Port 3000)</span>
                <span
                    class="w-2 h-2 rounded-full"
                    :class="isConnected ? 'bg-emerald-500' : 'bg-rose-500'"
                ></span>
            </button>

            <button
                @click="activeTab = 'email'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all"
                :class="activeTab === 'email'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <Mail class="w-4 h-4" />
                <span>SMTP Email Server</span>
            </button>
        </div>

        <!-- TAB 1: WHATSAPP GATEWAY -->
        <div v-if="activeTab === 'whatsapp'" class="space-y-6">
            <!-- Gateway Status Live Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-3 h-3 rounded-full animate-pulse" :class="isConnected ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Status Live WhatsApp Gateway</h3>
                    </div>
                    <button
                        @click="checkWhatsAppStatus"
                        :disabled="isCheckingWa"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-[11px] font-semibold text-slate-700 dark:text-slate-300 transition-colors"
                    >
                        <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isCheckingWa }" />
                        Periksa Ulang
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 text-[11px]">Koneksi Server</span>
                        <div class="mt-1 font-bold flex items-center gap-1.5" :class="waStatus?.status === 'ONLINE' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                            <CheckCircle2 v-if="waStatus?.status === 'ONLINE'" class="w-3.5 h-3.5" />
                            <XCircle v-else class="w-3.5 h-3.5" />
                            <span>{{ waStatus?.status || 'OFFLINE' }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 text-[11px]">Sesi WhatsApp</span>
                        <div class="mt-1 font-bold" :class="isConnected ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                            {{ isConnected ? 'Terhubung (Multi-Device Active)' : 'Menunggu Scan QR / Terputus' }}
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 text-[11px]">Target Endpoint</span>
                        <div class="mt-1 font-mono font-semibold text-slate-800 dark:text-slate-200 truncate">
                            {{ waGatewayUrl }}
                        </div>
                    </div>
                </div>

                <!-- Connected State Card -->
                <div v-if="isConnected" class="mt-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                            <CheckCircle2 class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-emerald-900 dark:text-emerald-200">
                                WhatsApp Gateway Aktif & Terhubung
                            </span>
                            <span class="text-[11px] text-emerald-700 dark:text-emerald-400">
                                Sistem siap mengirimkan pesan otomatis ke nomor WhatsApp pengguna.
                            </span>
                        </div>
                    </div>
                    <button
                        @click="handleResetSession"
                        :disabled="isResettingSession"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 text-[11px] font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-all shrink-0"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>Putus Sesi / Ganti Nomor</span>
                    </button>
                </div>

                <!-- QR Code Activation Scanner (Bisa langsung di-scan di Pengaturan) -->
                <div
                    v-else-if="waStatus?.qr || waStatus?.raw_qr"
                    class="mt-5 p-6 rounded-2xl bg-white dark:bg-slate-900 border-2 border-emerald-500/40 shadow-lg flex flex-col items-center text-center space-y-4"
                >
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-extrabold text-xs uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        SCAN QR CODE AKTIVASI WHATSAPP
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-300 max-w-md">
                        Buka <strong>WhatsApp di HP Anda</strong> &gt; Menu Titik Tiga (atau Pengaturan) &gt; <strong>Perangkat Tertaut</strong> &gt; <strong>Tautkan Perangkat</strong>, lalu arahkan kamera ke QR Code di bawah:
                    </p>

                    <!-- QR Code Canvas / Image -->
                    <div class="p-4 bg-white rounded-3xl shadow-xl border-4 border-slate-900 inline-block">
                        <img
                            v-if="qrImageSrc"
                            :src="qrImageSrc"
                            alt="Scan QR WhatsApp Gateway"
                            class="w-56 h-56 mx-auto object-contain"
                        />
                        <div v-else class="w-56 h-56 flex flex-col items-center justify-center text-slate-400 gap-2">
                            <RefreshCw class="w-8 h-8 animate-spin text-emerald-600" />
                            <span class="text-xs font-semibold">Memuat QR Code...</span>
                        </div>
                    </div>

                    <!-- Polling Status Badge -->
                    <div class="flex items-center gap-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        <RefreshCw class="w-3.5 h-3.5 animate-spin text-emerald-600" />
                        <span>Menunggu pemindaian... Layar ini otomatis terhubung seketika setelah di-scan.</span>
                    </div>

                    <!-- Action Controls -->
                    <div class="flex items-center gap-2 pt-1">
                        <button
                            @click="checkWhatsAppStatus(false)"
                            :disabled="isCheckingWa"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isCheckingWa }" />
                            <span>Refresh QR Manual</span>
                        </button>
                        <button
                            @click="handleResetSession"
                            :disabled="isResettingSession"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs font-semibold transition"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Reset & Buat QR Baru</span>
                        </button>
                    </div>
                </div>

                <div v-else-if="waStatus?.status === 'OFFLINE'" class="mt-3 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 text-[11px] text-rose-700 dark:text-rose-300">
                    ⚠️ Server WhatsApp Gateway pada <strong>{{ waGatewayUrl }}</strong> tidak merespon. Pastikan service Node.js WhatsApp Gateway telah berjalan (misal: via PM2 atau screen di port 3000).
                </div>
            </div>

            <!-- WhatsApp Configuration Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Shield class="w-4 h-4 text-emerald-500" />
                    Pengaturan Utama WhatsApp
                </h3>

                <!-- Master Toggle -->
                <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60">
                    <div>
                        <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">
                            Master Notifikasi WhatsApp (wa_notifications_enabled)
                        </span>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                            Matikan sakelar ini jika ingin menghentikan sementara seluruh pesan keluar WhatsApp dari sistem
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="waEnabled === '1'"
                            @change="waEnabled = ($event.target as HTMLInputElement).checked ? '1' : '0'"
                            class="sr-only peer"
                        />
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <!-- Gateway Base URL -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        URL WhatsApp Gateway API
                    </label>
                    <input
                        v-model="waGatewayUrl"
                        type="text"
                        placeholder="http://127.0.0.1:3000"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        Sesuai standar arsitektur `kantor`, gateway menyediakan endpoint <code>/send?number=...&msg=...</code> dan <code>/status-wa</code>.
                    </p>
                </div>
            </div>

            <!-- WhatsApp Test Send Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Send class="w-4 h-4 text-emerald-500" />
                    Uji Coba Pengiriman Pesan WhatsApp
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Nomor WhatsApp Tujuan (08... atau 628...)
                        </label>
                        <input
                            v-model="waTestPhone"
                            type="text"
                            placeholder="081234567890"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                        <span class="text-[10px] text-slate-500 dark:text-slate-400">
                            Sistem otomatis menormalisasi format nomor ke kode negara Indonesia (62).
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Pesan Kustom (Opsional)
                        </label>
                        <input
                            v-model="waTestMessage"
                            type="text"
                            placeholder="Tulis pesan pengujian atau biarkan kosong untuk pesan default"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        @click="sendWhatsAppTest"
                        :disabled="isSendingWaTest"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-md shadow-emerald-500/20 transition-all disabled:opacity-50"
                    >
                        <RefreshCw v-if="isSendingWaTest" class="w-3.5 h-3.5 animate-spin" />
                        <Send v-else class="w-3.5 h-3.5" />
                        <span>{{ isSendingWaTest ? 'Mengirim...' : 'Kirim Pesan Uji Coba' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 2: SMTP EMAIL SERVER -->
        <div v-if="activeTab === 'email'" class="space-y-6">
            <!-- SMTP Credentials Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Mail class="w-4 h-4 text-blue-500" />
                        Konfigurasi SMTP Mailer (ig-unfollow-agent Protocol)
                    </h3>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                        Dimuat dinamis saat runtime dari tabel settings
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Driver Mailer
                        </label>
                        <input
                            v-model="mailMailer"
                            type="text"
                            placeholder="smtp"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            SMTP Host
                        </label>
                        <input
                            v-model="mailHost"
                            type="text"
                            placeholder="smtp.gmail.com"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            SMTP Port
                        </label>
                        <input
                            v-model="mailPort"
                            type="number"
                            placeholder="587"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Protokol Enkripsi
                        </label>
                        <select
                            v-model="mailEncryption"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none"
                        >
                            <option value="tls">TLS (Direkomendasikan Port 587)</option>
                            <option value="ssl">SSL (Port 465)</option>
                            <option value="none">None (Tanpa Enkripsi)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Username / Akun Email
                        </label>
                        <input
                            v-model="mailUsername"
                            type="text"
                            placeholder="admin@simpan.site"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none"
                        />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Password / App Password
                        </label>
                        <div class="relative">
                            <input
                                v-model="mailPassword"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••••••••••"
                                class="w-full px-3.5 py-2.5 pr-10 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-900 dark:text-slate-100 outline-none"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <EyeOff v-if="showPassword" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                            Bila menggunakan Gmail / Google Workspace, buat <strong>App Password</strong> di Akun Google > Keamanan > Verifikasi 2 Langkah > Sandi Aplikasi.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            From Address (Pengirim)
                        </label>
                        <input
                            v-model="mailFromAddress"
                            type="email"
                            placeholder="no-reply@simpan.site"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            From Name (Nama Pengirim)
                        </label>
                        <input
                            v-model="mailFromName"
                            type="text"
                            placeholder="MyStorage Cloud"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- SMTP Test Send Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Send class="w-4 h-4 text-blue-500" />
                    Uji Coba Pengiriman Email SMTP
                </h3>

                <div class="max-w-md">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Alamat Email Tujuan Pengujian
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="mailTestEmail"
                            type="email"
                            placeholder="admin@simpan.site"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                        />
                        <button
                            @click="sendMailTest"
                            :disabled="isSendingMailTest"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-md shadow-blue-500/20 shrink-0 transition-all disabled:opacity-50"
                        >
                            <RefreshCw v-if="isSendingMailTest" class="w-3.5 h-3.5 animate-spin" />
                            <Send v-else class="w-3.5 h-3.5" />
                            <span>{{ isSendingMailTest ? 'Mengirim...' : 'Tes Email' }}</span>
                        </button>
                    </div>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 block">
                        Email uji coba akan dikirimkan dengan template responsive HTML MyStorage Cloud.
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
