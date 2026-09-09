<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import { useDriveStore } from '@/stores/drive';
import { useUiStore } from '@/stores/ui';
import type { FileItem } from '@/types';
import { Trash2, RotateCcw, AlertTriangle, FileText } from 'lucide-vue-next';

const drive = useDriveStore();
const ui = useUiStore();

const trashedFiles = ref<FileItem[]>([]);
const isLoading = ref(true);

async function loadTrash() {
    isLoading.value = true;
    try {
        const res = await http.get('/files', { params: { filter: 'trash' } });
        trashedFiles.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadTrash();
});

async function restore(file: FileItem) {
    await drive.restoreFile(file.id);
    trashedFiles.value = trashedFiles.value.filter(f => f.id !== file.id);
}

async function permanentDelete(file: FileItem) {
    if (confirm(`Yakin ingin menghapus "${file.original_name}" secara permanen? Data tidak dapat dikembalikan.`)) {
        await drive.permanentDeleteFile(file.id);
        trashedFiles.value = trashedFiles.value.filter(f => f.id !== file.id);
    }
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-4">
            <div class="flex items-center gap-2">
                <Trash2 class="w-5 h-5 text-rose-500" />
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Sampah</h2>
            </div>
        </div>

        <!-- 30-day policy notification -->
        <div class="flex items-center gap-3 p-3.5 mb-6 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/80 text-amber-900 dark:text-amber-200 text-xs">
            <AlertTriangle class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <span>Item di folder Sampah akan otomatis dihapus permanen setelah 30 hari sejak tanggal penghapusan.</span>
        </div>

        <div v-if="trashedFiles.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
            <div
                v-for="file in trashedFiles"
                :key="file.id"
                class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <FileText class="w-4 h-4 text-slate-400 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                            {{ file.original_name }}
                        </p>
                        <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                            {{ file.human_size }} • Dihapus pada: {{ new Date(file.deleted_at || '').toLocaleDateString() }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="restore(file)"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors"
                    >
                        <RotateCcw class="w-3.5 h-3.5 text-blue-500" />
                        <span>Pulihkan</span>
                    </button>
                    <button
                        @click="permanentDelete(file)"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/50 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-xs font-semibold text-rose-600 dark:text-rose-400 transition-colors"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Hapus Permanen</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-else-if="!isLoading" class="py-16 text-center text-slate-400 text-xs">
            Sampah kosong.
        </div>
    </div>
</template>
