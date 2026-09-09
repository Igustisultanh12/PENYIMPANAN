<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import type { FileItem } from '@/types';
import FileCard from '@/components/FileCard.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Clock } from 'lucide-vue-next';

const files = ref<FileItem[]>([]);
const isLoading = ref(true);
const activePreviewFile = ref<FileItem | null>(null);

async function loadRecent() {
    isLoading.value = true;
    try {
        const res = await http.get('/files', { params: { filter: 'recent' } });
        files.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadRecent();
});

function handleDownload(file: FileItem) {
    window.open(`/api/v1/files/${file.uuid}/preview`, '_blank');
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto">
        <div class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <Clock class="w-5 h-5 text-blue-500" />
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Berkas Terbaru</h2>
        </div>

        <div v-if="files.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3.5">
            <FileCard
                v-for="file in files"
                :key="file.uuid"
                :file="file"
                @preview="f => activePreviewFile = f"
                @download="handleDownload"
            />
        </div>

        <div v-else-if="!isLoading" class="py-16 text-center text-slate-400 text-xs">
            Tidak ada aktivitas berkas terbaru.
        </div>

        <FilePreviewModal
            :file="activePreviewFile"
            @close="activePreviewFile = null"
            @download="handleDownload"
        />
    </div>
</template>
