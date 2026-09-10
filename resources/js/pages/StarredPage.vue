<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import { downloadFile } from '@/utils/download';
import type { FileItem } from '@/types';
import FileCard from '@/components/FileCard.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import OfficeEditorModal from '@/components/OfficeEditorModal.vue';
import { Star } from 'lucide-vue-next';

const files = ref<FileItem[]>([]);
const isLoading = ref(true);
const activePreviewFile = ref<FileItem | null>(null);
const activeOfficeFile = ref<FileItem | null>(null);
const isOfficeModalOpen = ref(false);

async function loadStarred() {
    isLoading.value = true;
    try {
        const res = await http.get('/files', { params: { filter: 'starred' } });
        files.value = res.data.data;
    } catch {
        // Handle error
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadStarred();
});

function handleFileClick(file: FileItem) {
    const ext = (file.extension || '').toLowerCase();
    if (['docx', 'doc', 'xlsx', 'xls', 'csv', 'tsv', 'ods', 'pptx', 'ppt', 'txt', 'md', 'log'].includes(ext)) {
        activeOfficeFile.value = file;
        isOfficeModalOpen.value = true;
    } else {
        activePreviewFile.value = file;
    }
}

async function handleDownload(file: FileItem) {
    await downloadFile(file);
}

function handleOfficeSaved(file: FileItem) {
    const idx = files.value.findIndex(f => f.uuid === file.uuid);
    if (idx !== -1) {
        files.value[idx] = file;
    }
}
</script>

<template>
    <div class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto">
        <div class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <Star class="w-5 h-5 text-amber-400 fill-current" />
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Berkas Berbintang</h2>
        </div>

        <div v-if="files.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3.5">
            <FileCard
                v-for="file in files"
                :key="file.uuid"
                :file="file"
                @preview="handleFileClick"
                @download="handleDownload"
            />
        </div>

        <div v-else-if="!isLoading" class="py-16 text-center text-slate-400 text-xs">
            Belum ada berkas yang ditandai bintang.
        </div>

        <OfficeEditorModal
            v-model="isOfficeModalOpen"
            :file="activeOfficeFile"
            @saved="handleOfficeSaved"
        />

        <FilePreviewModal
            :file="activePreviewFile"
            @close="activePreviewFile = null"
            @download="handleDownload"
        />
    </div>
</template>
