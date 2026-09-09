<script setup lang="ts">
import { computed, ref } from 'vue';
import type { FileItem } from '@/types';
import {
    FileText,
    Image as ImageIcon,
    Video,
    Music,
    Archive,
    File as FileGeneric,
    MoreVertical,
    Star,
    Download,
    Share2,
    Eye,
    Edit3,
    CornerUpRight,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    file: FileItem;
}>();

const emit = defineEmits<{
    (e: 'preview', file: FileItem): void;
    (e: 'download', file: FileItem): void;
    (e: 'share', file: FileItem): void;
    (e: 'star', file: FileItem): void;
    (e: 'rename', file: FileItem): void;
    (e: 'move', file: FileItem): void;
    (e: 'delete', file: FileItem): void;
}>();

const showMenu = ref(false);

const categoryIcon = computed(() => {
    switch (props.file.category) {
        case 'image': return ImageIcon;
        case 'video': return Video;
        case 'audio': return Music;
        case 'document': return FileText;
        case 'archive': return Archive;
        default: return FileGeneric;
    }
});

const categoryColor = computed(() => {
    switch (props.file.category) {
        case 'image': return 'text-purple-500 bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800';
        case 'video': return 'text-rose-500 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800';
        case 'audio': return 'text-amber-500 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800';
        case 'document': return 'text-blue-500 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800';
        case 'archive': return 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800';
        default: return 'text-slate-500 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700';
    }
});

const previewUrl = computed(() => {
    return `/api/v1/files/${props.file.uuid}/preview`;
});
</script>

<template>
    <div
        @dblclick="emit('preview', file)"
        class="group relative bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden hover:shadow-md hover:border-blue-400 dark:hover:border-blue-600 transition-all flex flex-col select-none cursor-pointer"
    >
        <!-- Preview Area -->
        <div class="h-36 bg-slate-100/70 dark:bg-slate-800/50 flex items-center justify-center relative overflow-hidden">
            <!-- Image Thumbnail -->
            <img
                v-if="file.category === 'image' && file.status === 'ready'"
                :src="previewUrl"
                loading="lazy"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
            />

            <!-- Category Icon for Non-Images -->
            <div
                v-else
                class="w-14 h-14 rounded-2xl flex items-center justify-center border shadow-xs transition-transform group-hover:scale-110"
                :class="categoryColor"
            >
                <component :is="categoryIcon" class="w-7 h-7" />
            </div>

            <!-- Star Action Button -->
            <button
                @click.stop="emit('star', file)"
                class="absolute top-2.5 left-2.5 p-1.5 rounded-lg bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs text-slate-400 hover:text-amber-400 transition-colors shadow-xs"
                :class="{ 'opacity-100 !text-amber-400': file.is_starred, 'opacity-0 group-hover:opacity-100': !file.is_starred }"
            >
                <Star class="w-3.5 h-3.5" :class="{ 'fill-current': file.is_starred }" />
            </button>

            <!-- More Menu Button -->
            <button
                @click.stop="showMenu = !showMenu"
                class="absolute top-2.5 right-2.5 p-1.5 rounded-lg bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 opacity-0 group-hover:opacity-100 transition-opacity shadow-xs"
            >
                <MoreVertical class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- File Details Footer -->
        <div class="p-3.5 flex items-center justify-between gap-2 border-t border-slate-100 dark:border-slate-800/80">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                    {{ file.original_name }}
                </p>
                <div class="flex items-center gap-2 mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">
                    <span>{{ file.human_size }}</span>
                    <span>•</span>
                    <span class="uppercase font-mono">{{ file.extension }}</span>
                </div>
            </div>
        </div>

        <!-- Context Dropdown -->
        <div
            v-if="showMenu"
            @click.stop="showMenu = false"
            class="fixed inset-0 z-30"
        ></div>

        <div
            v-if="showMenu"
            @click.stop="showMenu = false"
            class="absolute right-3 top-10 w-44 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-1.5 z-40 text-xs"
        >
            <button
                @click="emit('preview', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Eye class="w-3.5 h-3.5 text-slate-400" />
                Pratinjau
            </button>
            <button
                @click="emit('download', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Download class="w-3.5 h-3.5 text-slate-400" />
                Unduh
            </button>
            <button
                @click="emit('share', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Share2 class="w-3.5 h-3.5 text-blue-500" />
                Bagikan
            </button>
            <button
                @click="emit('rename', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Edit3 class="w-3.5 h-3.5 text-slate-400" />
                Ganti Nama
            </button>
            <button
                @click="emit('move', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <CornerUpRight class="w-3.5 h-3.5 text-slate-400" />
                Pindahkan
            </button>
            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
            <button
                @click="emit('delete', file)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-left font-medium"
            >
                <Trash2 class="w-3.5 h-3.5" />
                Hapus
            </button>
        </div>
    </div>
</template>
