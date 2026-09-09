<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import {
    ShieldCheck,
    Key,
    Smartphone,
    Laptop,
    History,
    LogOut,
    CheckCircle2,
    Code,
    Plus,
    Trash2,
    Copy,
    X,
    Download,
} from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const activeTab = ref<'general' | 'sessions' | 'devices' | 'logs' | 'api'>('general');

// Desktop Client Devices & Modal
const desktopDevices = ref<any[]>([]);
const isLoadingDevices = ref(false);
const isDesktopModalOpen = ref(false);
const desktopConnectTab = ref<'app' | 'pwa' | 'network'>('app');

function downloadDesktopShortcut() {
    const content = `[InternetShortcut]\r\nURL=${window.location.origin}/drive\r\nIconIndex=0\r\n`;
    const blob = new Blob([content], { type: 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'MyStorage-Cloud.url';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    ui.notify('Shortcut desktop MyStorage berhasil diunduh.', 'success');
}

// Password state
const currentPassword = ref('');
const newPassword = ref('');
const newPasswordConfirmation = ref('');
const isChangingPassword = ref(false);

// 2FA state
const twoFactorSetupData = ref<{ secret: string; otp_url: string } | null>(null);
const verificationCode = ref('');
const recoveryCodes = ref<string[]>([]);
const isSettingUp2FA = ref(false);

// Sessions
const sessions = ref<any[]>([]);
const isLoadingSessions = ref(false);

// Audit logs
const auditLogs = ref<any[]>([]);
const isLoadingLogs = ref(false);

// API Tokens
const apiTokens = ref<any[]>([]);
const newTokenName = ref('');
const newlyCreatedToken = ref<string | null>(null);

async function handleChangePassword() {
    isChangingPassword.value = true;
    try {
        await http.post('/security/change-password', {
            current_password: currentPassword.value,
            new_password: newPassword.value,
            new_password_confirmation: newPasswordConfirmation.value,
        });
        ui.notify('Kata sandi berhasil diperbarui.', 'success');
        currentPassword.value = '';
        newPassword.value = '';
        newPasswordConfirmation.value = '';
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal mengubah kata sandi.', 'error');
    } finally {
        isChangingPassword.value = false;
    }
}

async function initTwoFactor() {
    isSettingUp2FA.value = true;
    try {
        const res = await http.post('/security/2fa/setup');
        twoFactorSetupData.value = res.data.data;
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal memulai setup 2FA.', 'error');
    }
}

async function confirmTwoFactor() {
    try {
        const res = await http.post('/security/2fa/confirm', { code: verificationCode.value });
        recoveryCodes.value = res.data.recovery_codes || [];
        twoFactorSetupData.value = null;
        if (auth.user) auth.user.two_factor_enabled = true;
        ui.notify('2FA berhasil diaktifkan!', 'success');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Kode verifikasi tidak valid.', 'error');
    }
}

async function loadSessions() {
    isLoadingSessions.value = true;
    try {
        const res = await http.get('/security/sessions');
        sessions.value = res.data.data;
    } finally {
        isLoadingSessions.value = false;
    }
}

async function logoutOtherDevices() {
    try {
        await http.post('/security/logout-other-devices');
        ui.notify('Berhasil logout dari semua perangkat lain.', 'info');
        loadSessions();
    } catch {
        ui.notify('Gagal melakukan logout perangkat lain.', 'error');
    }
}

async function loadAuditLogs() {
    isLoadingLogs.value = true;
    try {
        const res = await http.get('/security/audit-logs');
        auditLogs.value = res.data.data;
    } finally {
        isLoadingLogs.value = false;
    }
}

async function loadApiTokens() {
    try {
        const res = await http.get('/security/api-tokens');
        apiTokens.value = res.data.data;
    } catch {
        // Handle error
    }
}

async function createApiToken() {
    if (!newTokenName.value.trim()) return;
    try {
        const res = await http.post('/security/api-tokens', { name: newTokenName.value.trim() });
        newlyCreatedToken.value = res.data.data.token;
        newTokenName.value = '';
        loadApiTokens();
        ui.notify('API Token berhasil dibuat.', 'success');
    } catch {
        ui.notify('Gagal membuat API token.', 'error');
    }
}

async function revokeApiToken(id: number) {
    try {
        await http.delete(`/security/api-tokens/${id}`);
        apiTokens.value = apiTokens.value.filter(t => t.id !== id);
        ui.notify('Token berhasil dicabut.', 'info');
    } catch {
        ui.notify('Gagal mencabut token.', 'error');
    }
}

async function loadDesktopDevices() {
    isLoadingDevices.value = true;
    try {
        const res = await http.get('/devices');
        desktopDevices.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoadingDevices.value = false;
    }
}

async function revokeDevice(uuid: string) {
    if (!confirm('Cabut akses sinkronisasi perangkat ini? (Berkas di PC tetap tersimpan aman).')) return;
    try {
        await http.delete(`/devices/${uuid}`);
        ui.notify('Akses sinkronisasi perangkat berhasil dicabut.', 'info');
        loadDesktopDevices();
    } catch {
        ui.notify('Gagal mencabut perangkat.', 'error');
    }
}

onMounted(() => {
    loadSessions();
    loadDesktopDevices();
    loadAuditLogs();
    loadApiTokens();
});
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-4xl">
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div class="flex items-center gap-2">
                <ShieldCheck class="w-5 h-5 text-blue-500" />
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Pusat Keamanan Akun</h2>
            </div>

            <!-- Tabs -->
            <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
                <button
                    @click="activeTab = 'general'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'general' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    Keamanan Utama
                </button>
                <button
                    @click="activeTab = 'sessions'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'sessions' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    Sesi Web
                </button>
                <button
                    @click="activeTab = 'devices'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'devices' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    Perangkat Desktop
                </button>
                <button
                    @click="activeTab = 'logs'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'logs' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    Riwayat Audit
                </button>
                <button
                    @click="activeTab = 'api'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'api' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    API Key
                </button>
            </div>
        </div>

        <!-- Tab 1: General (Password & 2FA) -->
        <div v-if="activeTab === 'general'" class="space-y-6">
            <!-- 2FA Section -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Smartphone class="w-4 h-4 text-blue-500" />
                            Autentikasi Dua Langkah (2FA / TOTP)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xl leading-relaxed">
                            Tingkatkan proteksi akun MyStorage Anda dengan meminta kode verifikasi saat login dari perangkat yang belum dikenal.
                        </p>
                    </div>
                    <span
                        class="px-3 py-1 rounded-full text-xs font-semibold shrink-0"
                        :class="auth.user?.two_factor_enabled ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-500'"
                    >
                        {{ auth.user?.two_factor_enabled ? 'Aktif' : 'Belum Aktif' }}
                    </span>
                </div>

                <div v-if="!auth.user?.two_factor_enabled && !twoFactorSetupData" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button
                        @click="initTwoFactor"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
                    >
                        Aktifkan 2FA Sekarang
                    </button>
                </div>

                <!-- Setup 2FA Form -->
                <div v-if="twoFactorSetupData" class="mt-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                    <p class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                        1. Masukkan secret key ini ke aplikasi Authenticator (Google Authenticator / Authy):
                    </p>
                    <p class="font-mono text-sm bg-white dark:bg-slate-900 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 font-bold text-blue-600 tracking-wider">
                        {{ twoFactorSetupData.secret }}
                    </p>
                    <p class="text-xs text-slate-700 dark:text-slate-300 font-medium pt-2">
                        2. Masukkan kode 6 digit dari aplikasi untuk konfirmasi:
                    </p>
                    <div class="flex gap-2">
                        <input
                            v-model="verificationCode"
                            type="text"
                            placeholder="Contoh: 123456"
                            class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono outline-none"
                        />
                        <button
                            @click="confirmTwoFactor"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm"
                        >
                            Konfirmasi & Simpan
                        </button>
                    </div>
                </div>

                <!-- Recovery Codes -->
                <div v-if="recoveryCodes.length > 0" class="mt-4 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 space-y-2">
                    <p class="text-xs font-bold text-amber-800 dark:text-amber-200">Simpan Kode Pemulihan Cadangan Ini:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs text-amber-900 dark:text-amber-100 font-bold">
                        <span v-for="c in recoveryCodes" :key="c" class="bg-white/80 dark:bg-slate-900/80 p-1.5 rounded-lg text-center border">
                            {{ c }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Password Change Section -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-1">
                    <Key class="w-4 h-4 text-blue-500" />
                    Ubah Kata Sandi
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Gunakan kombinasi minimal 8 karakter dengan huruf besar, angka, dan simbol.
                </p>

                <form @submit.prevent="handleChangePassword" class="space-y-3 max-w-md">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kata Sandi Saat Ini</label>
                        <input
                            v-model="currentPassword"
                            type="password"
                            required
                            class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kata Sandi Baru</label>
                        <input
                            v-model="newPassword"
                            type="password"
                            required
                            class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Konfirmasi Kata Sandi Baru</label>
                        <input
                            v-model="newPasswordConfirmation"
                            type="password"
                            required
                            class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="isChangingPassword"
                        class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all disabled:opacity-50"
                    >
                        {{ isChangingPassword ? 'Memperbarui...' : 'Perbarui Kata Sandi' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab 2: Sessions -->
        <div v-else-if="activeTab === 'sessions'" class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-500">Perangkat yang sedang login ke akun Anda saat ini:</p>
                <button
                    @click="logoutOtherDevices"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-semibold hover:bg-rose-100 transition-colors"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    Logout Semua Perangkat Lain
                </button>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                <div v-for="s in sessions" :key="s.id" class="p-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Laptop class="w-5 h-5 text-slate-400" />
                        <div>
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate max-w-sm">
                                {{ s.user_agent || 'Peramban Web' }}
                            </p>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                IP: {{ s.ip_address }} • Aktif: {{ s.last_activity }}
                            </p>
                        </div>
                    </div>
                    <span v-if="s.is_current" class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-bold">
                        Sesi Saat Ini
                    </span>
                </div>
            </div>
        </div>

        <!-- Tab 3: Desktop Sync Devices -->
        <div v-else-if="activeTab === 'devices'" class="space-y-6">
            <!-- Desktop Sync Client Download Banner -->
            <div class="p-6 rounded-3xl bg-linear-to-r from-blue-600 to-indigo-700 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 text-white text-[11px] font-bold mb-2">
                        <span>Aplikasi Resmi Desktop</span>
                    </div>
                    <h3 class="text-lg font-bold">MyStorage Desktop Sync</h3>
                    <p class="text-xs text-blue-100 mt-1 max-w-lg leading-relaxed">
                        Sinkronisasi otomatis folder PC Anda secara langsung dengan cloud MyStorage. Mendukung background sync, deteksi konflik offline, dan selective folder sync.
                    </p>
                </div>
                <button
                    @click="isDesktopModalOpen = true"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white text-blue-700 hover:bg-blue-50 text-xs font-bold shadow-lg transition-all shrink-0 cursor-pointer"
                >
                    <Laptop class="w-4 h-4" />
                    <span>Unduh & Hubungkan PC</span>
                </button>
            </div>

            <!-- Connected Devices List -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Daftar Komputer Terhubung ({{ desktopDevices.length }})
                    </h4>
                    <button
                        @click="loadDesktopDevices"
                        class="text-xs text-blue-600 hover:underline"
                    >
                        Segarkan
                    </button>
                </div>

                <div v-if="desktopDevices.length === 0" class="p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800">
                    <Laptop class="w-8 h-8 text-slate-300 dark:text-slate-600 mx-auto mb-2" />
                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Belum ada perangkat PC yang terhubung.</p>
                    <p class="text-[11px] text-slate-400 mt-1">Unduh dan jalankan MyStorage Desktop untuk mengaktifkan sinkronisasi otomatis.</p>
                </div>

                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                    <div
                        v-for="d in desktopDevices"
                        :key="d.id"
                        class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                <Laptop class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                        {{ d.device_name }}
                                    </p>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-mono uppercase">
                                        {{ d.platform }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                        :class="d.status === 'active' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ d.status === 'active' ? 'Aktif' : 'Dicabut' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    IP: {{ d.ip_address || '-' }} • Terakhir Sinkron: {{ d.last_synced_at ? new Date(d.last_synced_at).toLocaleString('id-ID') : 'Baru terdaftar' }} • Versi: {{ d.client_version }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <button
                                v-if="d.status === 'active'"
                                @click="revokeDevice(d.uuid)"
                                class="px-3 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-xs font-semibold hover:bg-rose-100 transition-colors"
                            >
                                Cabut Akses
                            </button>
                            <span v-else class="text-xs text-slate-400 italic">
                                Akses telah dicabut
                            </span>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400 leading-relaxed px-1">
                    * Catatan: Mencabut akses perangkat hanya memutus otorisasi sinkronisasi ke cloud MyStorage. Berkas yang sudah tersimpan di hardisk PC Anda tidak akan dihapus.
                </p>
            </div>
        </div>

        <!-- Tab 4: Audit Logs -->
        <div v-else-if="activeTab === 'logs'" class="space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-400 font-semibold">
                            <th class="py-3 px-4">Aksi</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                            <td class="py-3 px-4 font-mono text-blue-600 dark:text-blue-400">{{ log.action }}</td>
                            <td class="py-3 px-4 font-mono text-slate-500">{{ log.ip_address || '--' }}</td>
                            <td class="py-3 px-4 text-slate-400">{{ new Date(log.created_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 4: API Tokens -->
        <div v-else-if="activeTab === 'api'" class="space-y-4">
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-1">
                    <Code class="w-4 h-4 text-blue-500" />
                    Personal API Tokens
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    Gunakan API token untuk mengintegrasikan MyStorage dengan skrip, bot, atau aplikasi pihak ketiga.
                </p>

                <!-- Create Form -->
                <div class="flex gap-2 max-w-md mb-4">
                    <input
                        v-model="newTokenName"
                        type="text"
                        placeholder="Nama Token (misal: CI/CD Uploader)"
                        class="flex-1 px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                    <button
                        @click="createApiToken"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm"
                    >
                        <Plus class="w-4 h-4" />
                        Buat Token
                    </button>
                </div>

                <!-- Newly Created Token Alert -->
                <div v-if="newlyCreatedToken" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 mb-4">
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-200 mb-1">Token Baru Anda:</p>
                    <p class="font-mono text-xs break-all bg-white dark:bg-slate-900 p-2.5 rounded-xl border select-all text-slate-800 dark:text-slate-200">
                        {{ newlyCreatedToken }}
                    </p>
                    <p class="text-[11px] text-emerald-700 mt-1 font-medium">⚠️ Harap salin token sekarang. Anda tidak akan bisa melihatnya lagi!</p>
                </div>

                <!-- Existing Tokens -->
                <div class="divide-y divide-slate-100 dark:divide-slate-800 border-t border-slate-100 dark:border-slate-800 pt-2">
                    <div v-for="t in apiTokens" :key="t.id" class="py-3 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ t.name }}</p>
                            <p class="text-[10px] text-slate-400 font-mono">Dibuat: {{ new Date(t.created_at).toLocaleDateString() }}</p>
                        </div>
                        <button
                            @click="revokeApiToken(t.id)"
                            class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Connection Guide Modal -->
    <Teleport to="body">
        <div
            v-if="isDesktopModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            @click.self="isDesktopModalOpen = false"
        >
            <div class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <Laptop class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Koneksikan MyStorage ke PC</h3>
                            <p class="text-xs text-slate-400">Pilih opsi integrasi komputer yang sesuai dengan kebutuhan Anda</p>
                        </div>
                    </div>
                    <button
                        @click="isDesktopModalOpen = false"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Method Tabs -->
                <div class="flex border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 p-1.5 gap-1 text-xs font-semibold shrink-0">
                    <button
                        @click="desktopConnectTab = 'app'"
                        class="flex-1 py-2 px-3 rounded-xl transition-all"
                        :class="desktopConnectTab === 'app' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                    >
                        1. Klien Desktop Sync
                    </button>
                    <button
                        @click="desktopConnectTab = 'pwa'"
                        class="flex-1 py-2 px-3 rounded-xl transition-all"
                        :class="desktopConnectTab === 'pwa' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                    >
                        2. Pasang App PC (PWA)
                    </button>
                    <button
                        @click="desktopConnectTab = 'network'"
                        class="flex-1 py-2 px-3 rounded-xl transition-all"
                        :class="desktopConnectTab === 'network' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                    >
                        3. Network Drive (Explorer)
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto space-y-4 text-xs">
                    <!-- Option 1: Desktop Sync Client -->
                    <div v-if="desktopConnectTab === 'app'" class="space-y-4">
                        <div class="p-4 rounded-2xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/50">
                            <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1">MyStorage Desktop Sync (Rust + Tauri)</h4>
                            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                                Aplikasi desktop ini berjalan di latar belakang Windows (*background tray icon*) untuk sinkronisasi otomatis dua arah antara folder komputer Anda dan cloud server.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <p class="font-semibold text-slate-700 dark:text-slate-300">Cara menjalankan / build di komputer PC Anda:</p>
                            <ol class="list-decimal list-inside space-y-1.5 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 font-mono text-[11px]">
                                <li>Buka terminal / PowerShell di folder proyek pada PC Anda</li>
                                <li class="font-bold text-blue-600 dark:text-blue-400">cd desktop</li>
                                <li class="font-bold text-blue-600 dark:text-blue-400">npm install</li>
                                <li class="font-bold text-blue-600 dark:text-blue-400">npm run dev (atau: npm run tauri build)</li>
                            </ol>
                        </div>

                        <div class="pt-2 flex flex-wrap items-center gap-3">
                            <button
                                @click="downloadDesktopShortcut"
                                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold transition-colors shadow-xs"
                            >
                                <Download class="w-4 h-4" />
                                <span>Unduh Shortcut Desktop (.url)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Option 2: PWA / Web App -->
                    <div v-else-if="desktopConnectTab === 'pwa'" class="space-y-4">
                        <div class="p-4 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50">
                            <h4 class="font-bold text-emerald-900 dark:text-emerald-200 text-sm mb-1">Pasang Aplikasi Instan (Tanpa Install Berat)</h4>
                            <p class="text-emerald-700 dark:text-emerald-400 leading-relaxed">
                                Anda dapat memasang MyStorage langsung menjadi aplikasi PC Windows dengan ikon di Desktop, Start Menu, dan Taskbar melalui browser Google Chrome atau Microsoft Edge.
                            </p>
                        </div>

                        <div class="space-y-2.5">
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
                                <span class="w-5 h-5 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">1</span>
                                <div>
                                    <p class="font-semibold text-slate-800 dark:text-slate-200">Buka di Browser Komputer</p>
                                    <p class="text-slate-500 text-[11px]">Buka tautan cloud ini di Google Chrome atau Microsoft Edge PC Anda.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
                                <span class="w-5 h-5 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">2</span>
                                <div>
                                    <p class="font-semibold text-slate-800 dark:text-slate-200">Pasang sebagai Aplikasi</p>
                                    <p class="text-slate-500 text-[11px]">Klik menu titik tiga <b>(⋮)</b> di kanan atas browser ➔ <b>Save and share (Simpan & bagikan)</b> ➔ <b>Install page as app (Pasang halaman ini sebagai aplikasi)</b>.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
                                <span class="w-5 h-5 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">3</span>
                                <div>
                                    <p class="font-semibold text-slate-800 dark:text-slate-200">Akses Mandiri</p>
                                    <p class="text-slate-500 text-[11px]">Aplikasi akan muncul di Desktop dan Taskbar Windows seperti software native.</p>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="downloadDesktopShortcut"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold transition-colors shadow-xs"
                        >
                            <Download class="w-4 h-4" />
                            <span>Unduh Shortcut Desktop (.url)</span>
                        </button>
                    </div>

                    <!-- Option 3: Network Drive -->
                    <div v-else class="space-y-4">
                        <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/50">
                            <h4 class="font-bold text-amber-900 dark:text-amber-200 text-sm mb-1">Hubungkan sebagai Partisi Drive di Windows Explorer</h4>
                            <p class="text-amber-800 dark:text-amber-400 leading-relaxed">
                                Muncul langsung sebagai partisi drive (seperti Drive <b>Z:</b>) di File Explorer komputer Anda. File Excel/Word bisa langsung diedit dengan Microsoft Office asli.
                            </p>
                        </div>

                        <div class="space-y-2 text-slate-600 dark:text-slate-400 text-xs">
                            <p class="font-semibold text-slate-800 dark:text-slate-200">Langkah Sambung di Windows Explorer:</p>
                            <ol class="list-decimal list-inside space-y-2 bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800">
                                <li>Tekan <kbd class="px-1.5 py-0.5 rounded-md bg-slate-200 dark:bg-slate-700 font-mono text-[10px]">Win + E</kbd> untuk membuka File Explorer di PC Anda.</li>
                                <li>Klik kanan pada <b>This PC</b> ➔ pilih <b>Map network drive...</b></li>
                                <li>Pilih huruf drive (misal <b>Z:</b>).</li>
                                <li>Masukkan alamat folder SMB server: <code class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-mono text-[11px]">\\192.168.1.29\storage</code></li>
                                <li>Centang <i>Reconnect at sign-in</i> lalu klik <b>Finish</b>.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-900/80 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        @click="isDesktopModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold transition-colors text-xs"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
