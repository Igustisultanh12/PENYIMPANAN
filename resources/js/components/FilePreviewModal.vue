<script setup lang="ts">
import { computed } from 'vue';
import type { FileItem } from '@/types';
import { X, Download, Share2, FileText } from 'lucide-vue-next';

const props = defineProps<{
    file: FileItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'download', file: FileItem): void;
    (e: 'share', file: FileItem): void;
}>();

const streamUrl = computed(() => {
    return props.file ? `/api/v1/files/${props.file.uuid}/preview` : '';
});
</script>

<template>
    <div
        v-if="file"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-sm transition-all"
    >
        <div class="relative w-full max-w-4xl h-[85vh] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <!-- Modal Header -->
            <div class="h-14 px-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
                <div class="flex items-center gap-3 min-w-0">
                    <FileText class="w-5 h-5 text-blue-500 shrink-0" />
                    <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                        {{ file.original_name }}
                    </span>
                    <span class="text-xs text-slate-400 font-mono">({{ file.human_size }})</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="emit('share', file)"
                        class="p-2 rounded-xl text-slate-500 hover:text-blue-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        title="Bagikan"
                    >
                        <Share2 class="w-4 h-4" />
                    </button>
                    <button
                        @click="emit('download', file)"
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        title="Unduh"
                    >
                        <Download class="w-4 h-4" />
                    </button>
                    <button
                        @click="emit('close')"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Preview Viewport -->
            <div class="flex-1 bg-slate-950/20 dark:bg-black/40 flex items-center justify-center p-4 overflow-hidden">
                <!-- Image Viewer -->
                <img
                    v-if="file.category === 'image'"
                    :src="streamUrl"
                    class="max-w-full max-h-full object-contain rounded-lg shadow-md"
                />

                <!-- Video Player -->
                <video
                    v-else-if="file.category === 'video'"
                    controls
                    autoplay
                    class="max-w-full max-h-full rounded-xl shadow-md outline-none"
                >
                    <source :src="streamUrl" :type="file.mime_type" />
                    Browser Anda tidak mendukung tag video.
                </video>

                <!-- Audio Player -->
                <div v-else-if="file.category === 'audio'" class="w-full max-w-md p-6 bg-white dark:bg-slate-800 rounded-2xl shadow-lg flex flex-col items-center">
                    <p class="text-sm font-semibold mb-4 text-slate-800 dark:text-slate-200">{{ file.original_name }}</p>
                    <audio controls class="w-full">
                        <source :src="streamUrl" :type="file.mime_type" />
                    </audio>
                </div>

                <!-- PDF Viewer -->
                <iframe
                    v-else-if="file.extension === 'pdf'"
                    :src="streamUrl"
                    class="w-full h-full rounded-xl border border-slate-200 dark:border-slate-800"
                ></iframe>

                <!-- Unsupported preview fallback -->
                <div v-else class="text-center p-8 max-w-sm">
                    <FileText class="w-16 h-16 text-slate-400 mx-auto mb-4 stroke-1" />
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1">Pratinjau Tidak Tersedia</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">
                        Format berkas ini ({{ file.extension.toUpperCase() }}) tidak dapat ditampilkan secara langsung di peramban.
                    </p>
                    <button
                        @click="emit('download', file)"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
                    >
                        Unduh Berkas Sekarang
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
