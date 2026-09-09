<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/utils/http';
import { useUiStore } from '@/stores/ui';
import type { FileItem, Folder } from '@/types';
import { X, CornerUpRight, Folder as FolderIcon, HardDrive } from 'lucide-vue-next';

const props = defineProps<{
    item: FileItem | Folder | null;
    itemType: 'file' | 'folder';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'moved'): void;
}>();

const ui = useUiStore();
const allFolders = ref<Folder[]>([]);
const selectedTargetUuid = ref<string | null>(null);
const isSubmitting = ref(false);

async function loadFolders() {
    try {
        const res = await http.get('/folders');
        allFolders.value = res.data.data;
    } catch {
        // Handle error
    }
}

onMounted(() => {
    loadFolders();
});

async function handleMove() {
    if (!props.item) return;

    isSubmitting.value = true;
    try {
        if (props.itemType === 'file') {
            await http.post(`/files/${props.item.uuid}/move`, {
                target_folder_uuid: selectedTargetUuid.value,
            });
        } else {
            await http.post(`/folders/${props.item.uuid}/move`, {
                target_parent_uuid: selectedTargetUuid.value,
            });
        }

        ui.notify('Item berhasil dipindahkan.', 'success');
        emit('moved');
        emit('close');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal memindahkan item.', 'error');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div
        v-if="item"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs transition-all"
    >
        <div class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <CornerUpRight class="w-5 h-5 text-blue-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pindahkan Item</h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="py-4 space-y-2 max-h-60 overflow-y-auto">
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Folder Tujuan:</p>

                <!-- Root Drive Option -->
                <button
                    type="button"
                    @click="selectedTargetUuid = null"
                    class="w-full flex items-center gap-3 p-2.5 rounded-xl border text-xs text-left transition-all"
                    :class="selectedTargetUuid === null
                        ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-600 dark:text-blue-400 font-semibold'
                        : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                >
                    <HardDrive class="w-4 h-4 text-blue-500 shrink-0" />
                    <span>Drive Saya (Root)</span>
                </button>

                <!-- Subfolders List -->
                <template v-for="folder in allFolders" :key="folder.uuid">
                    <button
                        v-if="folder.uuid !== item.uuid"
                        type="button"
                        @click="selectedTargetUuid = folder.uuid"
                        class="w-full flex items-center gap-3 p-2.5 rounded-xl border text-xs text-left transition-all"
                        :class="selectedTargetUuid === folder.uuid
                            ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-600 dark:text-blue-400 font-semibold'
                            : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    >
                        <FolderIcon class="w-4 h-4 text-slate-400 shrink-0" />
                        <span class="truncate">{{ folder.name }}</span>
                    </button>
                </template>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="handleMove"
                    :disabled="isSubmitting"
                    class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all disabled:opacity-50"
                >
                    {{ isSubmitting ? 'Memindahkan...' : 'Pindahkan ke Sini' }}
                </button>
            </div>
        </div>
    </div>
</template>
