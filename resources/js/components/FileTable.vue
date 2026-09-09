<script setup lang="ts">
import { ref, computed } from 'vue';
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
    CornerUpRight,
    Trash2,
    MoreVertical,
} from 'lucide-vue-next';
import { useContextMenu } from '@/composables/useContextMenu';

const props = defineProps<{
    files: FileItem[];
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

const contextFile = ref<FileItem | null>(null);

const isMenuOpen = computed(() => {
    return contextFile.value ? isOpen('table-' + contextFile.value.uuid) : false;
});

function handleRowContextMenu(e: MouseEvent, file: FileItem) {
    e.preventDefault();
    e.stopPropagation();
    contextFile.value = file;
    openMenu('table-' + file.uuid, e.clientX, e.clientY, 210, 310);
}

function handleMoreClick(e: MouseEvent, file: FileItem) {
    e.preventDefault();
    e.stopPropagation();
    contextFile.value = file;
    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    openMenu('table-' + file.uuid, rect.right - 210, rect.bottom + 6, 210, 310);
}

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
                    @contextmenu="handleRowContextMenu($event, file)"
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
                                @click.stop="emit('move', file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600"
                                title="Pindahkan"
                            >
                                <CornerUpRight class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="emit('delete', file)"
                                class="p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600"
                                title="Hapus"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click.stop="handleMoreClick($event, file)"
                                class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600"
                                title="Opsi"
                            >
                                <MoreVertical class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Floating Portal Context Menu for Table -->
    <Teleport to="body">
        <div
            v-if="isMenuOpen && contextFile"
            class="fixed inset-0 z-[99998] pointer-events-auto"
            @click="closeMenu"
            @contextmenu.prevent="closeMenu"
        >
            <div
                :style="{ left: `${menuPosition.x}px`, top: `${menuPosition.y}px` }"
                @click.stop
                class="fixed w-52 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl py-1.5 z-[99999] text-xs font-medium text-slate-700 dark:text-slate-200 select-none transition-all duration-100 animate-in fade-in zoom-in-95"
            >
                <!-- Menu Header -->
                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-slate-800/80 mb-1">
                    <p class="text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate" :title="contextFile.original_name">
                        {{ contextFile.original_name }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                        {{ contextFile.human_size }} • {{ (contextFile.extension || '').toUpperCase() }}
                    </p>
                </div>

                <!-- Action Items -->
                <div class="px-1 space-y-0.5">
                    <button
                        @click="emit('preview', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left"
                    >
                        <Eye class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Pratinjau / Buka</span>
                    </button>
                    <button
                        @click="emit('download', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Download class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Unduh Berkas</span>
                    </button>
                    <button
                        @click="emit('share', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Share2 class="w-3.5 h-3.5 text-blue-500 shrink-0" />
                        <span>Bagikan Tautan</span>
                    </button>
                    <button
                        @click="emit('star', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Star class="w-3.5 h-3.5 text-amber-500 shrink-0" :class="{ 'fill-amber-500': contextFile.is_starred }" />
                        <span>{{ contextFile.is_starred ? 'Hapus Bintang' : 'Bintangi Berkas' }}</span>
                    </button>
                    <button
                        @click="emit('rename', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <Edit3 class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Ganti Nama</span>
                    </button>
                    <button
                        @click="emit('move', contextFile); closeMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <CornerUpRight class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Pindahkan ke Folder</span>
                    </button>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800/80 my-1"></div>

                <div class="px-1">
                    <button
                        @click="emit('delete', contextFile); closeMenu()"
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
