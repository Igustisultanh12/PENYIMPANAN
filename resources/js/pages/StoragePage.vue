<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import type { StorageStats } from '@/types';
import {
    Database,
    Image as ImageIcon,
    Video,
    FileText,
    Music,
    Archive,
    FolderArchive,
    RefreshCw,
} from 'lucide-vue-next';

const stats = ref<StorageStats | null>(null);
const isLoading = ref(true);

async function loadStats() {
    isLoading.value = true;
    try {
        const res = await http.get('/storage/stats');
        stats.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

async function recalculate() {
    isLoading.value = true;
    try {
        await http.post('/storage/recalculate');
        await loadStats();
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadStats();
});
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto max-w-5xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div class="flex items-center gap-2">
                <Database class="w-5 h-5 text-blue-500" />
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Statistik Penyimpanan</h2>
            </div>
            <button
                @click="recalculate"
                :disabled="isLoading"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors"
            >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
                <span>Hitung Ulang</span>
            </button>
        </div>

        <div v-if="stats" class="space-y-6">
            <!-- Main Quota Card -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Penggunaan</p>
                        <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                            {{ stats.human_readable.used }} <span class="text-sm font-normal text-slate-400">dari {{ stats.human_readable.quota }}</span>
                        </h3>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold font-mono"
                            :class="{
                                'bg-emerald-50 text-emerald-600 border border-emerald-200': stats.status === 'normal',
                                'bg-amber-50 text-amber-600 border border-amber-200': stats.status === 'warning',
                                'bg-rose-50 text-rose-600 border border-rose-200': stats.status === 'critical' || stats.status === 'blocked',
                            }"
                        >
                            {{ stats.percentage }}% Terpakai
                        </span>
                        <p class="text-xs text-slate-400 mt-1">Tersedia {{ stats.human_readable.free }}</p>
                    </div>
                </div>

                <!-- Large Multi-Color Progress Bar -->
                <div class="w-full h-3.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex shadow-inner">
                    <div
                        class="h-full bg-purple-500 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.images.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Gambar"
                    ></div>
                    <div
                        class="h-full bg-rose-500 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.videos.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Video"
                    ></div>
                    <div
                        class="h-full bg-blue-500 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.documents.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Dokumen"
                    ></div>
                    <div
                        class="h-full bg-amber-500 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.audio.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Audio"
                    ></div>
                    <div
                        class="h-full bg-emerald-500 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.archives.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Arsip"
                    ></div>
                    <div
                        class="h-full bg-slate-400 transition-all duration-500"
                        :style="{ width: `${stats.quota_bytes > 0 ? (stats.categories.other.bytes / stats.quota_bytes) * 100 : 0}%` }"
                        title="Lainnya"
                    ></div>
                </div>
            </div>

            <!-- Category Breakdown Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 flex items-center justify-center shrink-0">
                        <ImageIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Gambar</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.images.formatted }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 flex items-center justify-center shrink-0">
                        <Video class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Video</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.videos.formatted }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 flex items-center justify-center shrink-0">
                        <FileText class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Dokumen</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.documents.formatted }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center shrink-0">
                        <Music class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Audio</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.audio.formatted }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 flex items-center justify-center shrink-0">
                        <Archive class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Arsip & Berkas Zip</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.archives.formatted }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center shrink-0">
                        <FolderArchive class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Lain-lain</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.categories.other.formatted }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
