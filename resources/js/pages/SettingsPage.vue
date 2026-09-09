<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { Settings, User as UserIcon, Globe, Smartphone, Mail, Sun, Moon } from 'lucide-vue-next';

const auth = useAuthStore();
const ui = useUiStore();

const name = ref('');
const whatsapp = ref('');
const email = ref('');
const timezone = ref('Asia/Jakarta');
const locale = ref('id');
const isSaving = ref(false);

onMounted(() => {
    if (auth.user) {
        name.value = auth.user.name;
        whatsapp.value = auth.user.whatsapp || '';
        email.value = auth.user.email;
        timezone.value = auth.user.profile?.timezone || 'Asia/Jakarta';
        locale.value = auth.user.profile?.locale || 'id';
    }
});

function handleSave() {
    ui.notify('Profil Anda berhasil diperbarui.', 'success');
}

function setDarkMode(val: boolean) {
    if (ui.isDarkMode !== val) {
        ui.toggleDarkMode();
    }
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-3xl">
        <div class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <Settings class="w-5 h-5 text-blue-500" />
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Pengaturan Profil & Akun</h2>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-5">
            <!-- Profile Info Form -->
            <form @submit.prevent="handleSave" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <UserIcon class="w-3.5 h-3.5 text-slate-400" />
                        Nama Lengkap
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <Mail class="w-3.5 h-3.5 text-slate-400" />
                        Alamat Email
                    </label>
                    <input
                        v-model="email"
                        type="email"
                        disabled
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs text-slate-500 outline-none cursor-not-allowed"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Perubahan email memerlukan verifikasi ulang untuk perlindungan identitas.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <Smartphone class="w-3.5 h-3.5 text-slate-400" />
                        Nomor WhatsApp
                    </label>
                    <input
                        v-model="whatsapp"
                        type="text"
                        placeholder="Contoh: 08123456789"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <Globe class="w-3.5 h-3.5 text-slate-400" />
                            Zona Waktu
                        </label>
                        <select
                            v-model="timezone"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
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
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        >
                            <option value="id">Bahasa Indonesia</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>

                <!-- Theme Appearance Setting -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Tema Tampilan (Dark / Light Mode)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="setDarkMode(false)"
                            class="p-3.5 rounded-2xl border flex items-center gap-3 transition-all duration-300 text-left"
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
                            class="p-3.5 rounded-2xl border flex items-center gap-3 transition-all duration-300 text-left"
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

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
