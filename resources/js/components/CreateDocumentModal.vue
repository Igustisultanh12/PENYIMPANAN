<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    X,
    FileText,
    Sheet,
    Presentation,
    Loader2,
    Sparkles,
    Check
} from 'lucide-vue-next';
import http from '../utils/http';
import { useUiStore } from '../stores/ui';
import type { FileItem } from '../types';

const props = defineProps<{
    modelValue: boolean;
    folderUuid?: string | null;
    defaultType?: 'document' | 'spreadsheet' | 'presentation';
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
    (e: 'created', file: FileItem): void;
}>();

const ui = useUiStore();

const isSubmitting = ref(false);
const docName = ref('');
const selectedType = ref<'document' | 'spreadsheet' | 'presentation'>('document');
const templates = ref<any[]>([]);
const selectedTemplate = ref<string | null>(null);
const isLoadingTemplates = ref(false);

const docTypes = [
    {
        id: 'document' as const,
        label: 'Dokumen Teks',
        ext: '.docx',
        icon: FileText,
        color: 'text-blue-600 bg-blue-50 dark:bg-blue-950/60'
    },
    {
        id: 'spreadsheet' as const,
        label: 'Lembar Sebar',
        ext: '.xlsx',
        icon: Sheet,
        color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60'
    },
    {
        id: 'presentation' as const,
        label: 'Presentasi Slide',
        ext: '.pptx',
        icon: Presentation,
        color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/60'
    },
];

watch(() => props.modelValue, (open) => {
    if (open) {
        selectedType.value = props.defaultType || 'document';
        docName.value = '';
        fetchTemplates();
    }
});

watch(selectedType, () => {
    fetchTemplates();
});

async function fetchTemplates() {
    isLoadingTemplates.value = true;
    try {
        const res = await http.get(`/api/v1/office/templates?type=${selectedType.value}`);
        templates.value = res.data.data;
        selectedTemplate.value = templates.value[0]?.name || null;
    } catch {
        // Fallback defaults
        templates.value = [];
    } finally {
        isLoadingTemplates.value = false;
    }
}

async function handleCreate() {
    if (!docName.value.trim()) {
        ui.addToast('Harap masukkan nama dokumen.', 'warning');
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await http.post('/api/v1/office/create', {
            name: docName.value.trim(),
            type: selectedType.value,
            folder_uuid: props.folderUuid || null,
        });

        ui.addToast('Dokumen baru berhasil dibuat.', 'success');
        emit('created', res.data.data);
        emit('update:modelValue', false);
    } catch (err: any) {
        ui.addToast(err.response?.data?.message || 'Gagal membuat dokumen.', 'error');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <Sparkles class="w-5 h-5 text-blue-600" />
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm">Buat Dokumen Office Baru</h3>
                </div>
                <button
                    @click="$emit('update:modelValue', false)"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <form @submit.prevent="handleCreate" class="p-6 space-y-5">
                <!-- Type Selection -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Tipe Dokumen</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            v-for="t in docTypes"
                            :key="t.id"
                            type="button"
                            @click="selectedType = t.id"
                            class="flex flex-col items-center p-3 rounded-xl border text-center transition-all cursor-pointer"
                            :class="selectedType === t.id
                                ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 shadow-xs'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
                        >
                            <div class="p-2 rounded-xl mb-2" :class="t.color">
                                <component :is="t.icon" class="w-5 h-5" />
                            </div>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ t.label }}</span>
                            <span class="text-[10px] text-slate-400 font-mono mt-0.5">{{ t.ext }}</span>
                        </button>
                    </div>
                </div>

                <!-- Document Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Nama Dokumen</label>
                    <input
                        v-model="docName"
                        type="text"
                        required
                        autofocus
                        placeholder="misal: Laporan Keuangan Q3"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <!-- Template Selection -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Pilih Template</label>
                    <div v-if="isLoadingTemplates" class="py-4 flex justify-center text-slate-400">
                        <Loader2 class="w-5 h-5 animate-spin" />
                    </div>
                    <div v-else class="grid grid-cols-1 gap-2 max-h-44 overflow-y-auto pr-1">
                        <div
                            v-for="tpl in templates"
                            :key="tpl.name"
                            @click="selectedTemplate = tpl.name"
                            class="flex items-center justify-between p-2.5 rounded-xl border transition-all cursor-pointer text-xs"
                            :class="selectedTemplate === tpl.name
                                ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 font-semibold'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
                        >
                            <div>
                                <p class="text-slate-800 dark:text-slate-200">{{ tpl.name }}</p>
                                <p class="text-[11px] text-slate-400 font-normal">{{ tpl.description }}</p>
                            </div>
                            <div v-if="selectedTemplate === tpl.name" class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center">
                                <Check class="w-3 h-3" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="$emit('update:modelValue', false)"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex items-center gap-1.5 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                        <span>Buat Dokumen</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
