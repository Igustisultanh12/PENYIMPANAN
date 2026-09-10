<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
    LogOut,
    Smartphone,
    Trash2,
    Key,
    Copy,
    Check,
    Phone,
    Database,
    HardDrive,
    Activity,
    Gauge,
    Zap,
    Thermometer,
    Sliders,
    Search,
    Edit3,
    ArrowDown,
    ArrowUp,
    Globe,
    Wifi,
    Cpu,
    Clock,
    X,
    Play,
} from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const activeTab = ref<'storage' | 'ssd' | 'speedtest' | 'whatsapp' | 'email'>('storage');
const isLoading = ref(true);
const isSaving = ref(false);
const showPassword = ref(false);

// Tab 1: Storage Quota Management
const defaultStorageQuotaGb = ref('10');
const isSavingDefaultQuota = ref(false);
const usersList = ref<any[]>([]);
const isLoadingUsers = ref(false);
const userSearch = ref('');
const selectedUserForQuota = ref<any | null>(null);
const targetUserQuotaGb = ref<number>(10);
const isUpdatingUserQuota = ref(false);
const isRecalculatingUser = ref<number | null>(null);
const quotaPresets = [5, 10, 25, 50, 100, 250, 500, 1000];

// Tab 2: SSD Health & SMART Monitor
const ssdHealthData = ref<any | null>(null);
const isLoadingSsdHealth = ref(false);

// Tab 3: Speedtest Ookla
const speedtestResult = ref<any | null>(null);
const isRunningSpeedtest = ref(false);
const speedtestProgress = ref(0);

// WhatsApp Form State
const waEnabled = ref('1');
const waGatewayUrl = ref('http://127.0.0.1:3000');
const waStatus = ref<{
    status: string;
    connected?: boolean;
    pairing_code?: string | null;
    phone?: string | null;
    message?: string;
} | null>(null);
const isCheckingWa = ref(false);
const isResettingSession = ref(false);
const pairingPhone = ref('');
const pairingCode = ref<string | null>(null);
const isRequestingPairing = ref(false);
const isCopied = ref(false);
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

const pairingCodeChars = computed(() => {
    if (!pairingCode.value) return [];
    return String(pairingCode.value).replace(/[^a-zA-Z0-9]/g, '').split('');
});

async function loadSettings() {
    isLoading.value = true;
    try {
        const res = await http.get('/admin/settings');
        const data = res.data.data;

        defaultStorageQuotaGb.value = String(data.default_storage_quota_gb ?? '10');
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
            if (!pairingPhone.value) {
                pairingPhone.value = auth.user.whatsapp;
            }
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

        if (res.data.data?.pairing_code && !pairingCode.value) {
            pairingCode.value = res.data.data.pairing_code;
        }
        if (res.data.data?.connected) {
            pairingCode.value = null;
        }
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

async function handleRequestPairing() {
    if (!pairingPhone.value) {
        ui.notify('Harap masukkan nomor WhatsApp admin (contoh: 081234567890).', 'error');
        return;
    }

    isRequestingPairing.value = true;
    try {
        const res = await http.post('/admin/whatsapp/pairing-code', {
            phone: pairingPhone.value,
        });

        if (res.data.pairing_code) {
            pairingCode.value = res.data.pairing_code;
            ui.notify('Kode pairing berhasil didapatkan! Masukkan kode ini pada WhatsApp di HP Anda.', 'success');
        } else {
            ui.notify(res.data.message || 'Permintaan kode pairing diproses.', 'info');
        }
        await checkWhatsAppStatus(true);
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal meminta kode pairing. Pastikan service gateway berjalan.', 'error');
    } finally {
        isRequestingPairing.value = false;
    }
}

function copyPairingCode() {
    if (!pairingCode.value) return;
    navigator.clipboard.writeText(pairingCode.value);
    isCopied.value = true;
    ui.notify('Kode pairing berhasil disalin!', 'success');
    setTimeout(() => {
        isCopied.value = false;
    }, 2500);
}

async function handleResetSession() {
    if (!confirm('Putus sesi WhatsApp saat ini dan bersihkan koneksi untuk pairing ulang?')) {
        return;
    }

    isResettingSession.value = true;
    try {
        const res = await http.post('/admin/whatsapp/reset');
        ui.notify(res.data.message || 'Sesi WhatsApp di-reset. Siap untuk pairing baru.', 'info');
        pairingCode.value = null;
        waStatus.value = { status: 'OFFLINE', connected: false, pairing_code: null };
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
            default_storage_quota_gb: defaultStorageQuotaGb.value,
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

// User Storage Functions
async function loadUsers() {
    isLoadingUsers.value = true;
    try {
        const res = await http.get('/admin/users', {
            params: { search: userSearch.value || undefined },
        });
        usersList.value = res.data.data?.data || [];
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal memuat daftar pengguna.', 'error');
    } finally {
        isLoadingUsers.value = false;
    }
}

function openEditQuotaModal(user: any) {
    selectedUserForQuota.value = user;
    targetUserQuotaGb.value = user.storage?.quota_gb ?? 10;
}

function setPresetQuota(gb: number) {
    targetUserQuotaGb.value = gb;
}

async function submitUserQuota() {
    if (!selectedUserForQuota.value) return;
    if (targetUserQuotaGb.value <= 0) {
        ui.notify('Kuota penyimpanan harus lebih dari 0 GB.', 'error');
        return;
    }

    isUpdatingUserQuota.value = true;
    try {
        const res = await http.post(`/admin/users/${selectedUserForQuota.value.id}/quota`, {
            quota_gb: targetUserQuotaGb.value,
        });

        ui.notify(res.data.message || 'Kuota penyimpanan berhasil diperbarui!', 'success');

        const u = usersList.value.find(item => item.id === selectedUserForQuota.value.id);
        if (u) {
            u.storage.quota_bytes = res.data.data.quota_bytes;
            u.storage.quota_gb = res.data.data.quota_gb;
            u.storage.quota_formatted = res.data.data.quota_formatted;
            u.storage.percentage = u.storage.quota_bytes > 0
                ? Math.min(100, Math.round((u.storage.used_bytes / u.storage.quota_bytes) * 100))
                : 0;
        }

        selectedUserForQuota.value = null;
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal memperbarui kuota pengguna.', 'error');
    } finally {
        isUpdatingUserQuota.value = false;
    }
}

async function recalculateUser(user: any) {
    isRecalculatingUser.value = user.id;
    try {
        const res = await http.post(`/admin/users/${user.id}/recalculate`);
        ui.notify(res.data.message || 'Penggunaan berhasil dihitung ulang!', 'success');

        const u = usersList.value.find(item => item.id === user.id);
        if (u) {
            u.storage.used_bytes = res.data.data.total_bytes_used;
            u.storage.used_formatted = res.data.data.used_formatted;
            u.storage.percentage = res.data.data.percentage;
        }
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal menghitung ulang penyimpanan.', 'error');
    } finally {
        isRecalculatingUser.value = null;
    }
}

async function saveDefaultStorageQuota() {
    const quotaVal = parseFloat(defaultStorageQuotaGb.value);
    if (isNaN(quotaVal) || quotaVal <= 0) {
        ui.notify('Harap masukkan angka kuota default yang valid (minimal 0.1 GB).', 'error');
        return;
    }

    isSavingDefaultQuota.value = true;
    try {
        await http.post('/admin/settings', {
            default_storage_quota_gb: defaultStorageQuotaGb.value,
        });
        ui.notify(`Kuota default pendaftaran berhasil diatur ke ${defaultStorageQuotaGb.value} GB!`, 'success');
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal menyimpan kuota default.', 'error');
    } finally {
        isSavingDefaultQuota.value = false;
    }
}

// SSD Health Functions
async function loadSsdHealth() {
    isLoadingSsdHealth.value = true;
    try {
        const res = await http.get('/admin/system/ssd-health');
        ssdHealthData.value = res.data.data;
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal memuat status kesehatan SSD.', 'error');
    } finally {
        isLoadingSsdHealth.value = false;
    }
}

const ssdHealthBadgeClass = computed(() => {
    const hp = ssdHealthData.value?.ssd?.health_percentage ?? 100;
    if (hp >= 90) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800';
    if (hp >= 70) return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 dark:border-blue-800';
    if (hp >= 50) return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800';
    return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-800';
});

const tempBadgeClass = computed(() => {
    const t = ssdHealthData.value?.ssd?.temperature_celsius ?? 38;
    if (t < 45) return 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50';
    if (t < 60) return 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50';
    return 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50';
});

// Speedtest Ookla Functions
async function runSpeedtest() {
    isRunningSpeedtest.value = true;
    speedtestProgress.value = 15;

    const progressTimer = setInterval(() => {
        if (speedtestProgress.value < 85) {
            speedtestProgress.value += 5;
        }
    }, 450);

    try {
        const res = await http.post('/admin/system/speedtest');
        speedtestProgress.value = 100;
        speedtestResult.value = res.data.data;
        ui.notify('Uji kecepatan internet server selesai!', 'success');
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal menjalankan Speedtest.', 'error');
    } finally {
        clearInterval(progressTimer);
        setTimeout(() => {
            isRunningSpeedtest.value = false;
        }, 500);
    }
}

watch(activeTab, (tab) => {
    if (tab === 'storage') {
        loadUsers();
    } else if (tab === 'ssd') {
        loadSsdHealth();
    } else if (tab === 'whatsapp') {
        checkWhatsAppStatus(false);
    }
});

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
    loadUsers();
    loadSsdHealth();

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
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-6xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-600/10 dark:bg-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <Server class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        Admin Panel & Pemantauan Sistem
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                            Administrator
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Kelola alokasi kuota penyimpanan pengguna, pantau kesehatan SSD, uji kecepatan Ookla, dan konfigurasi integrasi
                    </p>
                </div>
            </div>

            <!-- Global Save Button for Settings tabs -->
            <button
                v-if="activeTab === 'whatsapp' || activeTab === 'email'"
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
        <div class="flex items-center gap-2 mb-6 border-b border-slate-200 dark:border-slate-800 overflow-x-auto select-none">
            <!-- 1. Storage Tab -->
            <button
                @click="activeTab = 'storage'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all shrink-0"
                :class="activeTab === 'storage'
                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <Database class="w-4 h-4" />
                <span>Penyimpanan Pengguna</span>
            </button>

            <!-- 2. SSD Health Tab -->
            <button
                @click="activeTab = 'ssd'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all shrink-0"
                :class="activeTab === 'ssd'
                    ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <Activity class="w-4 h-4" />
                <span>Kesehatan SSD & Disk</span>
                <span
                    v-if="ssdHealthData?.ssd?.health_percentage"
                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300"
                >
                    {{ ssdHealthData.ssd.health_percentage }}%
                </span>
            </button>

            <!-- 3. Speedtest Ookla Tab -->
            <button
                @click="activeTab = 'speedtest'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all shrink-0"
                :class="activeTab === 'speedtest'
                    ? 'border-violet-500 text-violet-600 dark:text-violet-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <Gauge class="w-4 h-4" />
                <span>Speedtest Ookla</span>
            </button>

            <!-- 4. WhatsApp Tab -->
            <button
                @click="activeTab = 'whatsapp'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all shrink-0"
                :class="activeTab === 'whatsapp'
                    ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <MessageSquare class="w-4 h-4" />
                <span>WhatsApp Gateway</span>
                <span
                    class="w-2 h-2 rounded-full"
                    :class="isConnected ? 'bg-emerald-500' : 'bg-rose-500'"
                ></span>
            </button>

            <!-- 5. Email Tab -->
            <button
                @click="activeTab = 'email'"
                class="flex items-center gap-2 px-4 py-3 text-xs font-semibold border-b-2 transition-all shrink-0"
                :class="activeTab === 'email'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-slate-300'"
            >
                <Mail class="w-4 h-4" />
                <span>SMTP Email Server</span>
            </button>
        </div>

        <!-- ================= TAB 1: USER STORAGE & QUOTAS ================= -->
        <div v-if="activeTab === 'storage'" class="space-y-6">
            <!-- Global Default Quota Setting Card -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                            <Sliders class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Kuota Penyimpanan Bawaan Akun Baru</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Kapasitas penyimpanan awal yang otomatis didapatkan setiap pengguna baru saat mendaftar
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative w-36">
                            <input
                                v-model="defaultStorageQuotaGb"
                                type="number"
                                min="0.1"
                                step="1"
                                class="w-full pl-3 pr-9 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-900 dark:text-slate-100 outline-hidden focus:ring-2 focus:ring-blue-500"
                            />
                            <span class="absolute right-3 top-2.5 text-xs font-bold text-slate-400">GB</span>
                        </div>
                        <button
                            @click="saveDefaultStorageQuota"
                            :disabled="isSavingDefaultQuota"
                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <RefreshCw v-if="isSavingDefaultQuota" class="w-3.5 h-3.5 animate-spin" />
                            <Save v-else class="w-3.5 h-3.5" />
                            <span>Terapkan</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Users List with Quota Controls -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Database class="w-4 h-4 text-blue-500" />
                            Alokasi Kuota Penyimpanan Per Pengguna
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ usersList.length }} Pengguna
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Atur kuota khusus untuk masing-masing pengguna (misal: naikkan ke 50 GB, 100 GB, atau 1 TB)
                        </p>
                    </div>

                    <!-- Search Box & Refresh -->
                    <div class="flex items-center gap-2">
                        <div class="relative w-60">
                            <Search class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400" />
                            <input
                                v-model="userSearch"
                                @input="loadUsers"
                                type="text"
                                placeholder="Cari nama atau email..."
                                class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-hidden focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <button
                            @click="loadUsers"
                            :disabled="isLoadingUsers"
                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors"
                            title="Segarkan data pengguna"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoadingUsers }" />
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Pengguna</th>
                                <th class="px-4 py-3">Peran (Role)</th>
                                <th class="px-4 py-3">Penyimpanan Terpakai</th>
                                <th class="px-4 py-3">Kuota Diberikan</th>
                                <th class="px-4 py-3 w-40">Persentase</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                            <tr v-if="isLoadingUsers">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    <RefreshCw class="w-5 h-5 animate-spin mx-auto mb-2 text-blue-500" />
                                    <span>Memuat data pengguna...</span>
                                </td>
                            </tr>
                            <tr v-else-if="usersList.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada data pengguna yang sesuai.
                                </td>
                            </tr>
                            <tr
                                v-for="user in usersList"
                                :key="user.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs shrink-0">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ user.name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase"
                                        :class="user.role === 'admin' || user.role === 'super_admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ user.storage?.used_formatted }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 px-2 py-0.5 rounded-lg border border-blue-200 dark:border-blue-900/60">
                                        {{ user.storage?.quota_formatted }} ({{ user.storage?.quota_gb }} GB)
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
                                            <span>{{ user.storage?.percentage }}%</span>
                                        </div>
                                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="user.storage?.percentage >= 90 ? 'bg-rose-500' : user.storage?.percentage >= 80 ? 'bg-amber-500' : 'bg-blue-600'"
                                                :style="{ width: `${Math.min(100, user.storage?.percentage || 0)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openEditQuotaModal(user)"
                                            class="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/60 dark:hover:bg-blue-900/80 text-blue-600 dark:text-blue-300 text-xs font-semibold transition-colors flex items-center gap-1"
                                            title="Ubah batas kuota penyimpanan pengguna ini"
                                        >
                                            <Edit3 class="w-3 h-3" />
                                            <span>Ubah Kuota</span>
                                        </button>
                                        <button
                                            @click="recalculateUser(user)"
                                            :disabled="isRecalculatingUser === user.id"
                                            class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 transition-colors"
                                            title="Hitung ulang ukuran berkas fisik aktual"
                                        >
                                            <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isRecalculatingUser === user.id }" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Ubah Kuota Pengguna -->
            <div
                v-if="selectedUserForQuota"
                class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <Database class="w-5 h-5 text-blue-500" />
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Atur Kuota Penyimpanan</h3>
                        </div>
                        <button
                            @click="selectedUserForQuota = null"
                            class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- User Detail Summary -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl space-y-1">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ selectedUserForQuota.name }}</div>
                        <div class="text-[11px] text-slate-400">{{ selectedUserForQuota.email }}</div>
                        <div class="text-[11px] text-slate-500 pt-1">
                            Penggunaan saat ini: <strong>{{ selectedUserForQuota.storage.used_formatted }}</strong> dari <strong>{{ selectedUserForQuota.storage.quota_formatted }}</strong>
                        </div>
                    </div>

                    <!-- Preset Quota Buttons -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                            Pilih Preset Kuota:
                        </label>
                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="preset in quotaPresets"
                                :key="preset"
                                @click="setPresetQuota(preset)"
                                class="px-2 py-1.5 rounded-lg text-xs font-bold transition-all border"
                                :class="targetUserQuotaGb === preset
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                                    : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-blue-500'"
                            >
                                {{ preset >= 1000 ? `${preset / 1000} TB` : `${preset} GB` }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom GB Input -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Atau Masukkan Nilai Kuota Bebas (GB):
                        </label>
                        <div class="relative">
                            <input
                                v-model.number="targetUserQuotaGb"
                                type="number"
                                min="0.1"
                                step="1"
                                class="w-full pl-3 pr-10 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-900 dark:text-slate-100 outline-hidden focus:ring-2 focus:ring-blue-500"
                            />
                            <span class="absolute right-3 top-2.5 text-xs font-bold text-slate-400">GB</span>
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">
                            Setara dengan {{ (targetUserQuotaGb * 1024).toLocaleString() }} MB
                        </span>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button
                            @click="selectedUserForQuota = null"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitUserQuota"
                            :disabled="isUpdatingUserQuota"
                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <RefreshCw v-if="isUpdatingUserQuota" class="w-3.5 h-3.5 animate-spin" />
                            <Save v-else class="w-3.5 h-3.5" />
                            <span>Simpan Kuota</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: SSD HEALTH & SMART MONITOR ================= -->
        <div v-else-if="activeTab === 'ssd'" class="space-y-6">
            <!-- Header Action -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <HardDrive class="w-4 h-4 text-emerald-500" />
                        Status Kesehatan & Diagnostik Solid State Drive (SSD)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Memantau persentase sisa usia pakai (wear level), sensor suhu, atribut SMART, dan partisi disk server
                    </p>
                </div>
                <button
                    @click="loadSsdHealth"
                    :disabled="isLoadingSsdHealth"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-1.5 transition-colors"
                >
                    <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoadingSsdHealth }" />
                    <span>Perbarui Data Sensor</span>
                </button>
            </div>

            <!-- Health Score Hero Card -->
            <div class="p-6 rounded-2xl bg-linear-to-br from-slate-900 via-slate-850 to-slate-900 text-white border border-slate-800 shadow-lg relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border" :class="ssdHealthBadgeClass">
                                {{ ssdHealthData?.ssd?.health_status || 'Sangat Baik (Optimal)' }}
                            </span>
                            <span class="text-xs text-slate-400 font-mono">
                                {{ ssdHealthData?.ssd?.type || 'NVMe / SATA SSD' }}
                            </span>
                        </div>

                        <h2 class="text-2xl font-black tracking-tight text-white">
                            {{ ssdHealthData?.ssd?.model || 'Solid State Drive' }}
                        </h2>

                        <div class="flex items-center gap-4 text-xs text-slate-400 font-mono">
                            <span>Device: <strong>{{ ssdHealthData?.ssd?.device || '/dev/sda' }}</strong></span>
                            <span v-if="ssdHealthData?.ssd?.serial">S/N: <strong>{{ ssdHealthData.ssd.serial }}</strong></span>
                        </div>
                    </div>

                    <!-- Health Percentage Dial -->
                    <div class="flex items-center gap-4 bg-white/5 border border-white/10 rounded-2xl p-4 shrink-0">
                        <div class="text-right">
                            <div class="text-4xl font-black tracking-tight text-emerald-400 font-mono">
                                {{ ssdHealthData?.ssd?.health_percentage ?? 98 }}%
                            </div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">
                                Sisa Kesehatan SSD
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                            <Activity class="w-6 h-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4 Metrics Sensor Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. SMART Status -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Status SMART</span>
                        <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ ssdHealthData?.ssd?.smart_status || 'PASSED' }}
                    </div>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium block">
                        Hardware Normal & Siap Pakai
                    </span>
                </div>

                <!-- 2. Suhu Sensor -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Suhu Drive</span>
                        <Thermometer class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>{{ ssdHealthData?.ssd?.temperature_celsius ?? 38 }} °C</span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold" :class="tempBadgeClass">
                            {{ (ssdHealthData?.ssd?.temperature_celsius ?? 38) < 45 ? 'Adem' : 'Normal' }}
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400 block">
                        Batas aman: di bawah 65°C
                    </span>
                </div>

                <!-- 3. Total Data Tertulis (TBW) -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Total Data Tertulis (TBW)</span>
                        <ArrowUp class="w-4 h-4 text-blue-500" />
                    </div>
                    <div class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ ssdHealthData?.ssd?.tbw_formatted || 'Normal' }}
                    </div>
                    <span class="text-[11px] text-slate-400 block">
                        Akumulasi beban penulisan sel NAND
                    </span>
                </div>

                <!-- 4. Jam Operasional -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Jam Operasional (POH)</span>
                        <Clock class="w-4 h-4 text-purple-500" />
                    </div>
                    <div class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ ssdHealthData?.ssd?.power_on_hours ? `${ssdHealthData.ssd.power_on_hours.toLocaleString()} Jam` : 'Normal' }}
                    </div>
                    <span class="text-[11px] text-slate-400 block">
                        Total jam menyala sejak baru
                    </span>
                </div>
            </div>

            <!-- Partition & Storage Space Bar -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Pemakaian Ruang Partisi Storage Server</h4>
                        <p class="text-xs text-slate-400 font-mono">{{ ssdHealthData?.disk?.path }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                            {{ ssdHealthData?.disk?.used_formatted }} / {{ ssdHealthData?.disk?.total_formatted }}
                        </span>
                        <span class="text-xs text-slate-400 block">
                            Tersisa: {{ ssdHealthData?.disk?.free_formatted }}
                        </span>
                    </div>
                </div>

                <div class="w-full bg-slate-100 dark:bg-slate-800 h-3 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all bg-emerald-500"
                        :style="{ width: `${ssdHealthData?.disk?.used_percentage || 0}%` }"
                    ></div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Terpakai: {{ ssdHealthData?.disk?.used_percentage }}%</span>
                    <span>Tersedia: {{ 100 - (ssdHealthData?.disk?.used_percentage || 0) }}%</span>
                </div>
            </div>

            <!-- Info Guide Banner -->
            <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-800/80 text-xs text-blue-900 dark:text-blue-200 flex items-start gap-3">
                <Shield class="w-4 h-4 shrink-0 mt-0.5 text-blue-600 dark:text-blue-400" />
                <div class="space-y-1">
                    <p class="font-bold">Informasi Diagnostik Hardware Armbian Linux:</p>
                    <p class="leading-relaxed">
                        Data sensor kesehatan di atas diekstraksi langsung dari kernel Linux dan utilitas <code>smartctl</code> (smartmontools).
                        Bila server Armbian Anda belum memiliki utilitas ini, cukup jalankan di terminal server:
                        <code class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 font-mono text-[11px]">sudo apt-get install -y smartmontools</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: SPEEDTEST OOKLA ================= -->
        <div v-else-if="activeTab === 'speedtest'" class="space-y-6">
            <!-- Speedtest Hero Launcher -->
            <div class="p-6 sm:p-8 rounded-3xl bg-linear-to-br from-slate-900 via-slate-850 to-slate-900 text-white border border-slate-800 shadow-xl relative overflow-hidden flex flex-col items-center text-center">
                <div class="absolute -top-20 -left-20 w-60 h-60 bg-violet-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-20 -right-20 w-60 h-60 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 max-w-xl space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-violet-300 text-xs font-bold backdrop-blur-xs">
                        <Zap class="w-3.5 h-3.5 text-amber-400" />
                        <span>Ookla Speedtest Engine & Bandwidth Diagnostic</span>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                        Uji Kecepatan Koneksi Internet Server
                    </h2>

                    <p class="text-xs sm:text-sm text-slate-300 font-light leading-relaxed">
                        Uji performa bandwidth server Armbian ke internet secara langsung. Mengukur kecepatan Download, Upload, Ping latency, dan Jitter.
                    </p>

                    <!-- Run Speedtest Button -->
                    <div class="pt-2">
                        <button
                            @click="runSpeedtest"
                            :disabled="isRunningSpeedtest"
                            class="px-8 py-3.5 rounded-2xl bg-linear-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-violet-500/30 transition-all flex items-center gap-3 mx-auto disabled:opacity-50 active:scale-98"
                        >
                            <RefreshCw v-if="isRunningSpeedtest" class="w-4 h-4 animate-spin" />
                            <Play v-else class="w-4 h-4 fill-white" />
                            <span>{{ isRunningSpeedtest ? 'Menguji Kecepatan Server...' : 'Mulai Speedtest Sekarang' }}</span>
                        </button>
                    </div>

                    <!-- Live Progress Bar when Running -->
                    <div v-if="isRunningSpeedtest" class="w-full max-w-sm mx-auto space-y-1.5 pt-2">
                        <div class="flex items-center justify-between text-[11px] text-violet-300 font-mono">
                            <span>Mengukur throughput...</span>
                            <span>{{ speedtestProgress }}%</span>
                        </div>
                        <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-linear-to-r from-violet-400 to-emerald-400 rounded-full transition-all duration-300"
                                :style="{ width: `${speedtestProgress}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Speedtest Results Dashboard -->
            <div v-if="speedtestResult" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Gauge class="w-4 h-4 text-violet-500" />
                        Hasil Pengujian Kecepatan Terakhir
                    </h3>
                    <span class="text-xs text-slate-400 font-mono">
                        {{ new Date(speedtestResult.timestamp).toLocaleString('id-ID') }}
                    </span>
                </div>

                <!-- Big Mbps Metric Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Download Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                            <span>KECEPATAN UNDUH (DOWNLOAD)</span>
                            <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600">
                                <ArrowDown class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                            {{ speedtestResult.download_mbps }} <span class="text-sm font-normal text-slate-400">Mbps</span>
                        </div>
                        <span class="text-[11px] text-slate-400 block">Throughput unduh ke server</span>
                    </div>

                    <!-- Upload Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                            <span>KECEPATAN UNGGAH (UPLOAD)</span>
                            <div class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600">
                                <ArrowUp class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="text-3xl font-black tracking-tight text-blue-600 dark:text-blue-400 font-mono">
                            {{ speedtestResult.upload_mbps }} <span class="text-sm font-normal text-slate-400">Mbps</span>
                        </div>
                        <span class="text-[11px] text-slate-400 block">Throughput kirim dari server</span>
                    </div>

                    <!-- Ping Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                            <span>LATENSI (PING)</span>
                            <Wifi class="w-4 h-4 text-violet-500" />
                        </div>
                        <div class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ speedtestResult.ping_ms }} <span class="text-sm font-normal text-slate-400">ms</span>
                        </div>
                        <span class="text-[11px] text-slate-400 block">Waktu respon paket jaringan</span>
                    </div>

                    <!-- Jitter Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                            <span>JITTER</span>
                            <Activity class="w-4 h-4 text-amber-500" />
                        </div>
                        <div class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ speedtestResult.jitter_ms }} <span class="text-sm font-normal text-slate-400">ms</span>
                        </div>
                        <span class="text-[11px] text-slate-400 block">Variasi stabilitas latensi</span>
                    </div>
                </div>

                <!-- Network & Server Information Details -->
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Informasi Jaringan & Server Ookla</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 block mb-0.5">Penyedia Layanan (ISP):</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ speedtestResult.isp }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Server Uji:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ speedtestResult.server_name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Lokasi Server:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ speedtestResult.server_location }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Engine Pengujian:</span>
                            <span class="font-bold text-violet-600 dark:text-violet-400">{{ speedtestResult.engine }}</span>
                        </div>
                    </div>

                    <!-- Official Ookla Share Link -->
                    <div v-if="speedtestResult.result_url" class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-500">Hasil resmi terverifikasi di server Ookla Speedtest:</span>
                        <a
                            :href="speedtestResult.result_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-300 text-xs font-semibold hover:bg-violet-100 transition-colors"
                        >
                            <span>Lihat Sertifikat Hasil Ookla</span>
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- Armbian Speedtest CLI Installation Guide -->
            <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-2">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                    <Globe class="w-4 h-4 text-blue-500" />
                    <span>Panduan Pasang CLI Resmi Ookla Speedtest di Armbian:</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                    Untuk akurasi pengujian tingkat enterprise dengan server resmi Ookla di Armbian Linux, Anda dapat memasang paket speedtest resmi melalui terminal server:
                </p>
                <div class="p-2.5 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto select-all">
                    sudo apt-get install -y speedtest-cli
                </div>
            </div>
        </div>

        <!-- TAB 4: WHATSAPP GATEWAY -->
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
                            {{ isConnected ? 'Terhubung (Multi-Device Active)' : 'Menunggu Pairing / Terputus' }}
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

                <!-- Pairing Code Card (Tanpa QR Code) -->
                <div
                    v-else
                    class="mt-5 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold text-xs uppercase tracking-wider mb-2">
                                <Key class="w-3.5 h-3.5 text-emerald-500" />
                                Aktivasi WhatsApp Via Kode Pairing
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                Hubungkan WhatsApp dengan Kode 8 Karakter (Tanpa QR)
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Cukup masukkan nomor WhatsApp admin di bawah, lalu ketik kode pairing di WhatsApp HP Anda.
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                @click="checkWhatsAppStatus(false)"
                                :disabled="isCheckingWa"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isCheckingWa }" />
                                <span>Cek Status</span>
                            </button>
                            <button
                                @click="handleResetSession"
                                :disabled="isResettingSession"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs font-semibold transition"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Reset Sesi</span>
                            </button>
                        </div>
                    </div>

                    <!-- Input Nomor Telepon & Tombol Generate Pairing Code -->
                    <div class="max-w-xl">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Nomor WhatsApp Admin / Pengirim Gateway
                        </label>
                        <div class="flex flex-col sm:flex-row items-stretch gap-2.5">
                            <div class="relative flex-1">
                                <Phone class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                <input
                                    v-model="pairingPhone"
                                    type="text"
                                    placeholder="Contoh: 081234567890"
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-mono focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    @keyup.enter="handleRequestPairing"
                                />
                            </div>
                            <button
                                @click="handleRequestPairing"
                                :disabled="isRequestingPairing || !pairingPhone"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition-all disabled:opacity-50"
                            >
                                <RefreshCw v-if="isRequestingPairing" class="w-4 h-4 animate-spin" />
                                <Key v-else class="w-4 h-4" />
                                <span>{{ isRequestingPairing ? 'Memproses...' : 'Dapatkan Kode Pairing' }}</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                            Format nomor bisa diawali dengan <code>08...</code> atau <code>628...</code>.
                        </p>
                    </div>

                    <!-- Kotak Display Kode Pairing -->
                    <div
                        v-if="pairingCode"
                        class="bg-slate-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl border border-slate-800 flex flex-col items-center text-center space-y-4"
                    >
                        <p class="text-[10px] sm:text-xs text-emerald-400 font-extrabold uppercase tracking-widest">
                            KODE PAIRING WHATSAPP ANDA
                        </p>

                        <!-- Karakter Kode Pairing dalam 2 Blok (4 digit - 4 digit persis seperti tampilan WhatsApp HP) -->
                        <div class="flex items-center justify-center gap-2 sm:gap-3 my-2">
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <span
                                    v-for="(char, index) in pairingCodeChars.slice(0, 4)"
                                    :key="'c1-' + index"
                                    class="bg-white text-slate-900 w-10 h-12 sm:w-12 sm:h-14 rounded-xl font-black text-2xl sm:text-3xl font-mono shadow-inner border-b-4 border-slate-300 flex items-center justify-center select-all"
                                >
                                    {{ char }}
                                </span>
                            </div>
                            <span class="text-slate-400 font-black text-2xl sm:text-3xl mx-0.5">-</span>
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <span
                                    v-for="(char, index) in pairingCodeChars.slice(4, 8)"
                                    :key="'c2-' + index"
                                    class="bg-white text-slate-900 w-10 h-12 sm:w-12 sm:h-14 rounded-xl font-black text-2xl sm:text-3xl font-mono shadow-inner border-b-4 border-slate-300 flex items-center justify-center select-all"
                                >
                                    {{ char }}
                                </span>
                            </div>
                        </div>

                        <!-- Tombol Copy, Minta Kode Baru, & Indikator Tunggu -->
                        <div class="flex flex-wrap items-center justify-center gap-2.5 pt-1">
                            <button
                                @click="copyPairingCode"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold transition border border-slate-700"
                            >
                                <Check v-if="isCopied" class="w-4 h-4 text-emerald-400" />
                                <Copy v-else class="w-4 h-4 text-slate-400" />
                                <span>{{ isCopied ? 'Tersalin!' : 'Salin Kode' }}</span>
                            </button>
                            <button
                                @click="handleRequestPairing"
                                :disabled="isRequestingPairing"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold transition border border-slate-700"
                            >
                                <RefreshCw class="w-4 h-4 text-blue-400" :class="{ 'animate-spin': isRequestingPairing }" />
                                <span>Minta Kode Baru</span>
                            </button>
                            <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-950/60 text-emerald-300 text-xs font-medium border border-emerald-800/40">
                                <RefreshCw class="w-3.5 h-3.5 animate-spin text-emerald-400" />
                                <span>Menunggu Anda mengetik di HP...</span>
                            </div>
                        </div>

                        <!-- Petunjuk Langkah Demi Langkah -->
                        <div class="w-full max-w-lg text-left bg-slate-950/70 p-4 rounded-2xl border border-slate-800 text-xs space-y-2 mt-4 text-slate-300">
                            <p class="font-bold text-slate-200">Cara Tautkan di WhatsApp HP:</p>
                            <ol class="list-decimal list-inside space-y-1.5 text-[11px] text-slate-400">
                                <li>Buka aplikasi <strong>WhatsApp</strong> di HP Anda.</li>
                                <li>Ketuk menu <strong>Titik Tiga</strong> (Android) atau <strong>Pengaturan</strong> (iPhone) &gt; <strong>Perangkat Tertaut</strong>.</li>
                                <li>Ketuk tombol <strong>Tautkan Perangkat</strong>.</li>
                                <li>Pilih tulisan <strong>"Tautkan dengan nomor telepon saja"</strong> (di bawah jendela pemindai kamera).</li>
                                <li>Masukkan <strong>8 karakter Kode Pairing</strong> di atas.</li>
                                <li>Setelah selesai, status di sini otomatis berubah menjadi <strong>Terhubung</strong>.</li>
                            </ol>
                            <div class="mt-2.5 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-[10px] text-amber-300 font-medium">
                                💡 <strong>Tips:</strong> Kode pairing hanya aktif sekitar 60 detik. Pastikan nomor HP yang dimasukkan adalah nomor WhatsApp akun yang sedang aktif di HP Anda. Jika kode hangus atau WhatsApp meminta kode baru, cukup klik tombol <strong>"Minta Kode Baru"</strong> di atas.
                            </div>
                        </div>
                    </div>

                    <div v-if="waStatus?.status === 'OFFLINE'" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 text-[11px] text-rose-700 dark:text-rose-300">
                        ⚠️ Server WhatsApp Gateway pada <strong>{{ waGatewayUrl }}</strong> tidak merespon. Pastikan service Node.js WhatsApp Gateway berjalan di port 3000 (contoh: via PM2 di aaPanel).
                    </div>
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
