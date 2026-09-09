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
} from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const activeTab = ref<'general' | 'sessions' | 'devices' | 'logs' | 'api'>('general');

// Desktop Client Devices
const desktopDevices = ref<any[]>([]);
const isLoadingDevices = ref(false);

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
                <a
                    href="#download-desktop"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white text-blue-700 hover:bg-blue-50 text-xs font-bold shadow-lg transition-all shrink-0"
                >
                    <Laptop class="w-4 h-4" />
                    <span>Unduh Klien PC</span>
                </a>
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
</template>
