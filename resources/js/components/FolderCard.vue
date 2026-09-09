<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import type { Folder } from '@/types';
import {
    Folder as FolderIcon,
    FolderOpen,
    MoreVertical,
    Edit3,
    CornerUpRight,
    Share2,
    Trash2
} from 'lucide-vue-next';
import { useContextMenu } from '@/composables/useContextMenu';

const props = defineProps<{
    folder: Folder;
}>();

const emit = defineEmits<{
    (e: 'rename', folder: Folder): void;
    (e: 'move', folder: Folder): void;
    (e: 'share', folder: Folder): void;
    (e: 'delete', folder: Folder): void;
}>();

const router = useRouter();
const { openMenu, closeMenu, isOpen, menuPosition } = useContextMenu();

const isMenuOpen = computed(() => isOpen('folder-' + props.folder.uuid));

function openFolder() {
    router.push({ path: '/drive', query: { folder: props.folder.uuid } });
}

function handleContextMenu(e: MouseEvent) {
    e.preventDefault();
    e.stopPropagation();
    openMenu('folder-' + props.folder.uuid, e.clientX, e.clientY, 200, 240);
}

function handleMoreClick(e: MouseEvent) {
    e.preventDefault();
    e.stopPropagation();
    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    openMenu('folder-' + props.folder.uuid, rect.right - 200, rect.bottom + 6, 200, 240);
}
</script>

<template>
    <div
        @dblclick="openFolder"
        @contextmenu="handleContextMenu"
        class="group relative bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 flex items-center justify-between gap-3 card-hover-lift hover:border-blue-500/60 dark:hover:border-blue-500/60 transition-all duration-300 cursor-pointer select-none"
    >
        <div class="flex items-center gap-3.5 min-w-0">
            <div
                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105"
                :style="{ backgroundColor: `${folder.color}20`, color: folder.color }"
            >
                <FolderIcon class="w-5 h-5 fill-current" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                    {{ folder.name }}
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    {{ folder.files_count || 0 }} berkas
                </p>
            </div>
        </div>

        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
                @click="handleMoreClick"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                title="Menu Folder"
            >
                <MoreVertical class="w-4 h-4" />
            </button>
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
                class="fixed w-48 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl py-1.5 z-[99999] text-xs font-medium text-slate-700 dark:text-slate-200 select-none transition-all duration-100 animate-in fade-in zoom-in-95"
            >
                <!-- Menu Header: Folder Name -->
                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-slate-800/80 mb-1">
                    <p class="text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate" :title="folder.name">
                        {{ folder.name }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                        {{ folder.files_count || 0 }} berkas
                    </p>
                </div>

                <!-- Action Items -->
                <div class="px-1 space-y-0.5">
                    <button
                        @click="openFolder(); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left"
                    >
                        <FolderOpen class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Buka Folder</span>
                    </button>
                    <button
                        @click="emit('share', folder); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Share2 class="w-3.5 h-3.5 text-blue-500 shrink-0" />
                        <span>Bagikan Folder</span>
                    </button>
                    <button
                        @click="emit('rename', folder); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Edit3 class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Ganti Nama</span>
                    </button>
                    <button
                        @click="emit('move', folder); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <CornerUpRight class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Pindahkan Folder</span>
                    </button>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800/80 my-1"></div>

                <div class="px-1">
                    <button
                        @click="emit('delete', folder); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors text-left font-medium"
                    >
                        <Trash2 class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                        <span>Hapus Folder</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
