<script setup lang="ts">
import { ref, watch } from 'vue';
import type { FileItem, Folder } from '@/types';
import { X, Edit3 } from 'lucide-vue-next';

const props = defineProps<{
    item: FileItem | Folder | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', newName: string): void;
}>();

const newName = ref('');

watch(() => props.item, (val) => {
    if (val) {
        newName.value = 'original_name' in val ? val.original_name : val.name;
    }
}, { immediate: true });

function handleSubmit() {
    if (newName.value.trim()) {
        emit('save', newName.value.trim());
        emit('close');
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
                    <Edit3 class="w-5 h-5 text-blue-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ganti Nama</h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="py-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Baru</label>
                    <input
                        v-model="newName"
                        type="text"
                        required
                        autofocus
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 outline-none"
                    />
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
                        :disabled="!newName.trim()"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all disabled:opacity-50"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
