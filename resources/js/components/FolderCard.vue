<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import type { Folder } from '@/types';
import { Folder as FolderIcon, MoreVertical, Star, Edit3, CornerUpRight, Share2, Trash2 } from 'lucide-vue-next';

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
const showMenu = ref(false);

function openFolder() {
    router.push({ path: '/drive', query: { folder: props.folder.uuid } });
}
</script>

<template>
    <div
        @dblclick="openFolder"
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
                @click.stop="showMenu = !showMenu"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
                <MoreVertical class="w-4 h-4" />
            </button>
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
            class="absolute right-3 top-12 w-44 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-1.5 z-40 text-xs"
        >
            <button
                @click="emit('share', folder)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Share2 class="w-3.5 h-3.5 text-blue-500" />
                Bagikan
            </button>
            <button
                @click="emit('rename', folder)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <Edit3 class="w-3.5 h-3.5 text-slate-400" />
                Ganti Nama
            </button>
            <button
                @click="emit('move', folder)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
            >
                <CornerUpRight class="w-3.5 h-3.5 text-slate-400" />
                Pindahkan
            </button>
            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
            <button
                @click="emit('delete', folder)"
                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-left font-medium"
            >
                <Trash2 class="w-3.5 h-3.5" />
                Hapus
            </button>
        </div>
    </div>
</template>
