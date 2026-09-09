<script setup lang="ts">
import { computed } from 'vue';
import type { FileItem } from '@/types';
import {
    FileText,
    Image as ImageIcon,
    Video,
    Music,
    Archive,
    File as FileGeneric,
    Star,
    Download,
    Share2,
    Eye,
    Edit3,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    files: FileItem[];
}>();

const emit = defineEmits<{
    (e: 'preview', file: FileItem): void;
    (e: 'download', file: FileItem): void;
    (e: 'share', file: FileItem): void;
    (e: 'star', file: FileItem): void;
    (e: 'rename', file: FileItem): void;
    (e: 'delete', file: FileItem): void;
}>();

function getIcon(category: string) {
    switch (category) {
        case 'image': return ImageIcon;
        case 'video': return Video;
        case 'audio': return Music;
        case 'document': return FileText;
        case 'archive': return Archive;
        default: return FileGeneric;
    }
}
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-400 dark:text-slate-500 font-semibold bg-slate-50/50 dark:bg-slate-900/50">
                    <th class="py-3 px-4">Nama</th>
                    <th class="py-3 px-4 hidden sm:table-cell">Ukuran</th>
                    <th class="py-3 px-4 hidden md:table-cell">Tipe</th>
                    <th class="py-3 px-4 hidden lg:table-cell">Terakhir Diubah</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                <tr
                    v-for="file in files"
                    :key="file.uuid"
                    @dblclick="emit('preview', file)"
                    class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors group cursor-pointer"
                >
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            <button
                                @click.stop="emit('star', file)"
                                class="text-slate-300 hover:text-amber-400 transition-colors"
                                :class="{ '!text-amber-400': file.is_starred }"
                            >
                                <Star class="w-4 h-4" :class="{ 'fill-current': file.is_starred }" />
                            </button>
                            <component :is="getIcon(file.category)" class="w-4 h-4 text-blue-500 shrink-0" />
                            <span class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-xs group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ file.original_name }}
                            </span>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-mono hidden sm:table-cell">
                        {{ file.human_size }}
                    </td>
                    <td class="py-3 px-4 uppercase text-slate-400 font-mono hidden md:table-cell">
                        {{ file.extension }}
                    </td>
                    <td class="py-3 px-4 text-slate-400 hidden lg:table-cell">
                        {{ new Date(file.updated_at).toLocaleDateString() }}
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                @click.stop="emit('preview', file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600"
                                title="Pratinjau"
                            >
                                <Eye class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="emit('download', file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600"
                                title="Unduh"
                            >
                                <Download class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="emit('share', file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-blue-500"
                                title="Bagikan"
                            >
                                <Share2 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="emit('rename', file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600"
                                title="Ganti Nama"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="emit('delete', file)"
                                class="p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600"
                                title="Hapus"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
