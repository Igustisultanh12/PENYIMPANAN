<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import { useUiStore } from '@/stores/ui';
import type { Share } from '@/types';
import { Users, Link2, Trash2, Copy, Check } from 'lucide-vue-next';

const ui = useUiStore();
const activeTab = ref<'owned' | 'received'>('owned');
const shares = ref<Share[]>([]);
const isLoading = ref(true);
const copiedToken = ref<string | null>(null);

async function loadShares() {
    isLoading.value = true;
    try {
        const res = await http.get('/shares', { params: { type: activeTab.value } });
        shares.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadShares();
});

async function revokeShare(share: Share) {
    try {
        await http.delete(`/shares/${share.uuid}`);
        shares.value = shares.value.filter(s => s.uuid !== share.uuid);
        ui.notify('Tautan berbagi berhasil dicabut.', 'info');
    } catch {
        ui.notify('Gagal mencabut tautan berbagi.', 'error');
    }
}

function copyLink(token: string) {
    const url = `${window.location.origin}/s/${token}`;
    navigator.clipboard.writeText(url);
    copiedToken.value = token;
    ui.notify('Tautan berhasil disalin!', 'info');
    setTimeout(() => { copiedToken.value = null; }, 2000);
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div class="flex items-center gap-2">
                <Users class="w-5 h-5 text-blue-500" />
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Berkas Dibagikan</h2>
            </div>

            <!-- Tab Switcher -->
            <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
                <button
                    @click="activeTab = 'owned'; loadShares()"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'owned' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    Dibagikan oleh Saya
                </button>
                <button
                    @click="activeTab = 'received'; loadShares()"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'received' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    Dibagikan kepada Saya
                </button>
            </div>
        </div>

        <div v-if="shares.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
            <div
                v-for="share in shares"
                :key="share.uuid"
                class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                        {{ share.item ? ('original_name' in share.item ? share.item.original_name : share.item.name) : 'Item Dibagikan' }}
                    </p>
                    <div class="flex items-center gap-3 mt-1 text-[11px] text-slate-400">
                        <span class="capitalize">Izin: {{ share.permission }}</span>
                        <span>•</span>
                        <span>{{ share.allow_download ? 'Boleh Unduh' : 'Hanya Lihat' }}</span>
                        <span v-if="share.expires_at">• Kedaluwarsa: {{ new Date(share.expires_at).toLocaleDateString() }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="copyLink(share.token)"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        <Check v-if="copiedToken === share.token" class="w-3.5 h-3.5 text-emerald-500" />
                        <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                        <span>{{ copiedToken === share.token ? 'Tersalin' : 'Salin Tautan' }}</span>
                    </button>
                    <button
                        v-if="activeTab === 'owned'"
                        @click="revokeShare(share)"
                        class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                        title="Cabut Akses"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <div v-else-if="!isLoading" class="py-16 text-center text-slate-400 text-xs">
            Tidak ada data berbagi pada bagian ini.
        </div>
    </div>
</template>
