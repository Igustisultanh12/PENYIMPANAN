<script setup lang="ts">
import { ref } from 'vue';
import { useDriveStore } from '@/stores/drive';
import { X, FolderPlus } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const drive = useDriveStore();
const folderName = ref('');
const selectedColor = ref('#3B82F6');
const isSubmitting = ref(false);

const colorPalette = [
    '#3B82F6', // Blue
    '#10B981', // Emerald
    '#8B5CF6', // Purple
    '#F59E0B', // Amber
    '#EF4444', // Red
    '#EC4899', // Pink
    '#6B7280', // Gray
];

async function handleCreate() {
    if (!folderName.value.trim()) return;

    isSubmitting.value = true;
    try {
        await drive.createFolder(folderName.value.trim(), selectedColor.value);
        folderName.value = '';
        emit('close');
    } catch {
        // Error handled in store
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs transition-all"
    >
        <div class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <FolderPlus class="w-5 h-5 text-blue-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Buat Folder Baru</h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <form @submit.prevent="handleCreate" class="py-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Folder</label>
                    <input
                        v-model="folderName"
                        type="text"
                        required
                        autofocus
                        placeholder="Misal: Dokumen Proyek"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 outline-none"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Warna Aksen</label>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="color in colorPalette"
                            :key="color"
                            type="button"
                            @click="selectedColor = color"
                            class="w-7 h-7 rounded-full transition-transform hover:scale-110 flex items-center justify-center"
                            :style="{ backgroundColor: color }"
                            :class="{ 'ring-2 ring-offset-2 ring-blue-500 dark:ring-offset-slate-900 scale-110': selectedColor === color }"
                        ></button>
                    </div>
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
                        type="submit"
                        :disabled="isSubmitting || !folderName.trim()"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all disabled:opacity-50"
                    >
                        {{ isSubmitting ? 'Membuat...' : 'Buat Folder' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
