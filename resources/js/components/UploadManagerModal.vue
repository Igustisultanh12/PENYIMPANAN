<script setup lang="ts">
import { computed, ref } from 'vue';
import { useUploadStore } from '@/stores/upload';
import {
    ChevronDown,
    ChevronUp,
    X,
    Pause,
    Play,
    RotateCcw,
    CheckCircle2,
    AlertCircle,
    Loader2,
} from 'lucide-vue-next';

const upload = useUploadStore();
const isMinimized = ref(false);

const activeCount = computed(() => {
    return upload.queue.filter(i => i.status === 'uploading' || i.status === 'pending').length;
});

const completedCount = computed(() => {
    return upload.queue.filter(i => i.status === 'completed').length;
});

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes > 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return `${bytes.toFixed(1)} ${units[i]}`;
}
</script>

<template>
    <div
        v-if="upload.queue.length > 0 && upload.isTrayOpen"
        class="fixed bottom-5 right-5 z-50 w-96 max-w-[calc(100vw-2.5rem)] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden transition-all duration-300"
    >
        <!-- Header -->
        <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold">
                    {{ activeCount > 0 ? `Mengunggah ${activeCount} berkas...` : `Selesai (${completedCount} berkas)` }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <button
                    @click="isMinimized = !isMinimized"
                    class="p-1 rounded text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                >
                    <ChevronDown v-if="!isMinimized" class="w-4 h-4" />
                    <ChevronUp v-else class="w-4 h-4" />
                </button>
                <button
                    @click="upload.isTrayOpen = false"
                    class="p-1 rounded text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Upload Items List -->
        <div v-if="!isMinimized" class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 p-2">
            <div
                v-for="item in upload.queue"
                :key="item.id"
                class="p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
            >
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <p class="text-xs font-medium text-slate-800 dark:text-slate-200 truncate flex-1" :title="item.name">
                        {{ item.name }}
                    </p>
                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Pause / Resume -->
                        <button
                            v-if="item.status === 'uploading'"
                            @click="upload.pauseUpload(item)"
                            class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            title="Jeda"
                        >
                            <Pause class="w-3.5 h-3.5" />
                        </button>
                        <button
                            v-else-if="item.status === 'paused' || item.status === 'error'"
                            @click="upload.resumeUpload(item)"
                            class="p-1 text-blue-500 hover:text-blue-700"
                            title="Lanjutkan"
                        >
                            <Play v-if="item.status === 'paused'" class="w-3.5 h-3.5" />
                            <RotateCcw v-else class="w-3.5 h-3.5" />
                        </button>

                        <!-- Cancel -->
                        <button
                            v-if="item.status !== 'completed'"
                            @click="upload.cancelUpload(item)"
                            class="p-1 text-slate-400 hover:text-rose-500"
                            title="Batalkan"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>

                        <!-- Completed Icon -->
                        <CheckCircle2 v-if="item.status === 'completed'" class="w-4 h-4 text-emerald-500" />
                        <AlertCircle v-else-if="item.status === 'error'" class="w-4 h-4 text-rose-500" />
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mb-1.5">
                    <div
                        class="h-full transition-all duration-300 rounded-full"
                        :class="{
                            'bg-blue-600': item.status === 'uploading',
                            'bg-emerald-500': item.status === 'completed',
                            'bg-amber-500': item.status === 'paused',
                            'bg-rose-500': item.status === 'error',
                        }"
                        :style="{ width: `${item.progress}%` }"
                    ></div>
                </div>

                <!-- Stats Footer (Bytes, Speed, ETA, Chunk) -->
                <div class="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                    <span>{{ formatBytes(item.uploadedBytes) }} / {{ formatBytes(item.size) }} ({{ item.progress }}%)</span>
                    <span v-if="item.status === 'uploading'">
                        {{ item.speed }} • ETA {{ item.eta }} (Chunk {{ item.currentChunk }}/{{ item.totalChunks }})
                    </span>
                    <span v-else-if="item.status === 'completed'" class="text-emerald-500 font-medium">Selesai</span>
                    <span v-else-if="item.status === 'paused'" class="text-amber-500 font-medium">Dijeda</span>
                    <span v-else-if="item.status === 'error'" class="text-rose-500 font-medium truncate max-w-[120px]">{{ item.error }}</span>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div v-if="!isMinimized && completedCount > 0" class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 text-right">
            <button
                @click="upload.clearCompleted"
                class="text-[11px] text-blue-600 dark:text-blue-400 font-medium hover:underline"
            >
                Bersihkan riwayat selesai
            </button>
        </div>
    </div>
</template>
