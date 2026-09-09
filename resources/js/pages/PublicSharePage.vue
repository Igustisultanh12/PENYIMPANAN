<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/utils/http';
import { useUiStore } from '@/stores/ui';
import { Cloud, Download, Lock, FileText, AlertCircle, Eye } from 'lucide-vue-next';

const route = useRoute();
const ui = useUiStore();

const token = route.params.token as string;
const shareData = ref<any>(null);
const isLoading = ref(true);
const passwordRequired = ref(false);
const password = ref('');
const errorMessage = ref('');

async function loadShare() {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const headers: Record<string, string> = {};
        if (password.value) {
            headers['X-Share-Password'] = password.value;
        }

        const res = await http.get(`/shares/public/${token}`, { headers });
        shareData.value = res.data.data;
        passwordRequired.value = false;
    } catch (error: any) {
        if (error.response?.data?.password_required) {
            passwordRequired.value = true;
        } else {
            errorMessage.value = error.response?.data?.message || 'Tautan berbagi tidak valid atau telah kedaluwarsa.';
        }
    } finally {
        isLoading.value = false;
    }
}

function handleDownload() {
    const url = `/api/v1/shares/public/${token}/download` + (password.value ? `?pwd=${encodeURIComponent(password.value)}` : '');
    window.location.href = url;
}

onMounted(() => {
    loadShare();
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950">
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-xl p-8">
            <!-- Brand -->
            <div class="flex items-center justify-between pb-6 border-b border-slate-100 dark:border-slate-800 mb-6">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-sm">
                        <Cloud class="w-4 h-4" />
                    </div>
                    <span class="text-sm font-bold text-slate-900 dark:text-white">MyStorage</span>
                </div>
                <span class="text-[11px] font-semibold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-full">
                    Tautan Berbagi
                </span>
            </div>

            <!-- Password Protected Gate -->
            <div v-if="passwordRequired" class="text-center py-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-3">
                    <Lock class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Tautan Dilindungi Kata Sandi</h3>
                <p class="text-xs text-slate-400 mt-1 mb-5">Masukkan kata sandi yang diberikan oleh pemilik berkas untuk mengakses.</p>

                <form @submit.prevent="loadShare" class="space-y-3 max-w-xs mx-auto">
                    <input
                        v-model="password"
                        type="password"
                        required
                        autofocus
                        placeholder="Ketik kata sandi..."
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-center outline-none"
                    />
                    <button
                        type="submit"
                        class="w-full py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all"
                    >
                        Buka Akses Berkas
                    </button>
                </form>
            </div>

            <!-- Error State -->
            <div v-else-if="errorMessage" class="text-center py-6">
                <AlertCircle class="w-12 h-12 text-rose-500 mx-auto mb-3" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Akses Tidak Tersedia</h3>
                <p class="text-xs text-slate-400">{{ errorMessage }}</p>
            </div>

            <!-- File Details & Download -->
            <div v-else-if="shareData" class="space-y-6">
                <div class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 flex items-center justify-center shrink-0">
                        <FileText class="w-6 h-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                            {{ shareData.item?.original_name || shareData.item?.name }}
                        </h4>
                        <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 font-mono">
                            <span v-if="shareData.item?.human_size">{{ shareData.item.human_size }}</span>
                            <span v-if="shareData.item?.extension">• {{ shareData.item.extension.toUpperCase() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Shared by info -->
                <div v-if="shareData.owner" class="flex items-center gap-2.5 text-xs text-slate-500">
                    <span>Dibagikan oleh:</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ shareData.owner.name }}</span>
                </div>

                <!-- Download Button -->
                <div class="pt-2">
                    <button
                        v-if="shareData.allow_download"
                        @click="handleDownload"
                        class="w-full py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2"
                    >
                        <Download class="w-4 h-4" />
                        <span>Unduh Berkas Sekarang</span>
                    </button>
                    <p v-else class="text-xs text-slate-400 text-center italic">
                        Pengunduhan fisik dinonaktifkan untuk tautan ini oleh pemilik berkas.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
