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
    MoreVertical,
    Star,
    Download,
    Share2,
    Eye,
    Edit3,
    CornerUpRight,
    Trash2,
} from 'lucide-vue-next';
import { useContextMenu } from '@/composables/useContextMenu';

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

const { openMenu, closeMenu, isOpen, menuPosition } = useContextMenu();

const isMenuOpen = computed(() => isOpen('file-' + props.file.uuid));

function handleContextMenu(e: MouseEvent) {
    e.preventDefault();
    e.stopPropagation();
    openMenu('file-' + props.file.uuid, e.clientX, e.clientY, 210, 310);
}

function handleMoreClick(e: MouseEvent) {
    e.preventDefault();
    e.stopPropagation();
    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    openMenu('file-' + props.file.uuid, rect.right - 210, rect.bottom + 6, 210, 310);
}

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
        @contextmenu="handleContextMenu"
        class="group relative bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden card-hover-lift hover:border-blue-500/60 dark:hover:border-blue-500/60 transition-all duration-300 flex flex-col select-none cursor-pointer"
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
                title="Bintangi"
            >
                <Star class="w-3.5 h-3.5" :class="{ 'fill-current': file.is_starred }" />
            </button>

            <!-- More Menu Button -->
            <button
                @click="handleMoreClick"
                class="absolute top-2.5 right-2.5 p-1.5 rounded-lg bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 opacity-0 group-hover:opacity-100 transition-opacity shadow-xs z-10"
                title="Menu Pilihan"
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
    </div>

    <!-- Floating Portal Context Menu -->
    <Teleport to="body">
        <div
            v-if="isMenuOpen"
            class="fixed inset-0 z-[99998] pointer-events-auto"
            @click="closeMenu"
            @contextmenu.prevent="closeMenu"
        >
            <div
                :style="{ left: `${menuPosition.x}px`, top: `${menuPosition.y}px` }"
                @click.stop
                class="fixed w-52 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl py-1.5 z-[99999] text-xs font-medium text-slate-700 dark:text-slate-200 select-none transition-all duration-100 animate-in fade-in zoom-in-95"
            >
                <!-- Menu Header: File Name & Size -->
                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-slate-800/80 mb-1">
                    <p class="text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate" :title="file.original_name">
                        {{ file.original_name }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                        {{ file.human_size }} • {{ (file.extension || '').toUpperCase() }}
                    </p>
                </div>

                <!-- Action Items -->
                <div class="px-1 space-y-0.5">
                    <button
                        @click="emit('preview', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left"
                    >
                        <Eye class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Pratinjau / Buka</span>
                    </button>
                    <button
                        @click="emit('download', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Download class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Unduh Berkas</span>
                    </button>
                    <button
                        @click="emit('share', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Share2 class="w-3.5 h-3.5 text-blue-500 shrink-0" />
                        <span>Bagikan Tautan</span>
                    </button>
                    <button
                        @click="emit('star', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Star class="w-3.5 h-3.5 text-amber-500 shrink-0" :class="{ 'fill-amber-500': file.is_starred }" />
                        <span>{{ file.is_starred ? 'Hapus Bintang' : 'Bintangi Berkas' }}</span>
                    </button>
                    <button
                        @click="emit('rename', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Edit3 class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Ganti Nama</span>
                    </button>
                    <button
                        @click="emit('move', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <CornerUpRight class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Pindahkan ke Folder</span>
                    </button>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800/80 my-1"></div>

                <div class="px-1">
                    <button
                        @click="emit('delete', file); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors text-left font-medium"
                    >
                        <Trash2 class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                        <span>Pindahkan ke Sampah</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
