<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import http from '@/utils/http';
import {
    Settings,
    User as UserIcon,
    Globe,
    Smartphone,
    Mail,
    Sun,
    Moon,
    Lock,
    Server,
    ArrowRight,
    RefreshCw,
    Save,
} from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const name = ref('');
const whatsapp = ref('');
const email = ref('');
const timezone = ref('Asia/Jakarta');
const locale = ref('id');
const isSaving = ref(false);

function syncUserData() {
    if (auth.user) {
        name.value = auth.user.name || '';
        whatsapp.value = auth.user.whatsapp || '';
        email.value = auth.user.email || '';
        timezone.value = auth.user.profile?.timezone || 'Asia/Jakarta';
        locale.value = auth.user.profile?.locale || 'id';
    }
}

watch(
    () => auth.user,
    () => {
        syncUserData();
    },
    { immediate: true, deep: true }
);

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser();
    }
    syncUserData();
});

async function handleSave() {
    if (!name.value.trim()) {
        ui.notify('Nama lengkap wajib diisi.', 'warning');
        return;
    }

    isSaving.value = true;
    try {
        const res = await http.put('/profile', {
            name: name.value.trim(),
            whatsapp: whatsapp.value.trim(),
            timezone: timezone.value,
            locale: locale.value,
        });

        if (res.data?.data) {
            auth.user = {
                ...auth.user,
                ...res.data.data,
            };
        }
        ui.notify('Profil Anda berhasil diperbarui dan disimpan.', 'success');
    } catch (err: any) {
        ui.notify(err.response?.data?.message || 'Gagal menyimpan pembaruan profil.', 'error');
    } finally {
        isSaving.value = false;
    }
}

function setDarkMode(val: boolean) {
    if (ui.isDarkMode !== val) {
        ui.toggleDarkMode();
    }
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-3xl">
        <!-- Page Header -->
        <div class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <Settings class="w-5 h-5 text-blue-500" />
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Pengaturan Profil & Akun</h2>
        </div>

        <!-- Administrator Navigation Callout Banner -->
        <div
            v-if="auth.user?.role === 'admin' || auth.user?.role === 'super_admin'"
            class="mb-6 p-4.5 rounded-2xl bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-violet-500/10 border border-blue-200 dark:border-blue-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-500/20 shrink-0">
                    <Server class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                        Mencari Fitur Kuota Storage, Speedtest, atau Cek SSD?
                    </h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Fitur administrator sistem dan kuota penyimpanan pengguna tersedia di tab <strong>Admin Panel</strong>.
                    </p>
                </div>
            </div>

            <router-link
                to="/admin/settings"
                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shrink-0 shadow-sm shadow-blue-500/20 transition-all hover:gap-2"
            >
                <span>Buka Admin Panel</span>
                <ArrowRight class="w-3.5 h-3.5" />
            </router-link>
        </div>

        <!-- Profile Settings Card -->
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-5">
            <form @submit.prevent="handleSave" class="space-y-4">
                <!-- Nama Lengkap -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <UserIcon class="w-3.5 h-3.5 text-slate-400" />
                        Nama Lengkap
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        placeholder="Masukkan nama lengkap Anda"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                    />
                </div>

                <!-- Alamat Email (Terkunci untuk keamanan) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <Mail class="w-3.5 h-3.5 text-slate-400" />
                            Alamat Email Akun
                        </label>
                        <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                            <Lock class="w-3 h-3 text-slate-400" />
                            Terkunci (Identitas Login)
                        </span>
                    </div>
                    <input
                        :value="email"
                        type="email"
                        readonly
                        placeholder="Memuat alamat email..."
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 outline-none cursor-default font-mono"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">
                        Alamat email digunakan sebagai kredensial login utama dan tidak dapat diubah sembarangan demi keamanan akun.
                    </p>
                </div>

                <!-- Nomor WhatsApp -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <Smartphone class="w-3.5 h-3.5 text-slate-400" />
                        Nomor WhatsApp
                    </label>
                    <input
                        v-model="whatsapp"
                        type="text"
                        placeholder="Contoh: 08123456789 atau 628123456789"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                    />
                </div>

                <!-- Zona Waktu & Bahasa -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <Globe class="w-3.5 h-3.5 text-slate-400" />
                            Zona Waktu
                        </label>
                        <select
                            v-model="timezone"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                        >
                            <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                            <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                            <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                            <option value="UTC">UTC Universal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bahasa</label>
                        <select
                            v-model="locale"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                        >
                            <option value="id">Bahasa Indonesia</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>

                <!-- Tema Tampilan (Dark / Light Mode) -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Tema Tampilan (Dark / Light Mode)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="setDarkMode(false)"
                            class="p-3.5 rounded-2xl border flex items-center gap-3 transition-all duration-300 text-left cursor-pointer"
                            :class="!ui.isDarkMode ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/30 text-blue-600 ring-2 ring-blue-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'"
                        >
                            <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-xs">
                                <Sun class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-xs font-bold">Mode Terang</p>
                                <p class="text-[10px] text-slate-400">Tampilan bersih & cerah</p>
                            </div>
                        </button>

                        <button
                            type="button"
                            @click="setDarkMode(true)"
                            class="p-3.5 rounded-2xl border flex items-center gap-3 transition-all duration-300 text-left cursor-pointer"
                            :class="ui.isDarkMode ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/30 text-blue-400 ring-2 ring-blue-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'"
                        >
                            <div class="w-8 h-8 rounded-xl bg-indigo-950 text-indigo-400 flex items-center justify-center shadow-xs">
                                <Moon class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-xs font-bold">Mode Gelap</p>
                                <p class="text-[10px] text-slate-400">Nyaman di mata saat malam</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition-all disabled:opacity-50 cursor-pointer"
                    >
                        <RefreshCw v-if="isSaving" class="w-3.5 h-3.5 animate-spin" />
                        <Save v-else class="w-3.5 h-3.5" />
                        <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
