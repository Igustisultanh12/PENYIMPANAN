<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useDriveStore } from '@/stores/drive';
import { ChevronRight, HardDrive } from 'lucide-vue-next';

const router = useRouter();
const drive = useDriveStore();

function navigate(uuid: string | null) {
    if (uuid) {
        router.push({ path: '/drive', query: { folder: uuid } });
    } else {
        router.push({ path: '/drive' });
    }
}
</script>

<template>
    <nav class="flex items-center gap-1.5 text-sm font-medium text-slate-600 dark:text-slate-400 overflow-x-auto py-1">
        <button
            @click="navigate(null)"
            class="flex items-center gap-1.5 hover:text-blue-600 dark:hover:text-blue-400 transition-colors shrink-0"
            :class="{ 'font-semibold text-slate-900 dark:text-white': !drive.currentFolderUuid }"
        >
            <HardDrive class="w-4 h-4" />
            <span>Drive Saya</span>
        </button>

        <template v-for="(item, index) in drive.breadcrumbs" :key="item.uuid">
            <ChevronRight class="w-3.5 h-3.5 text-slate-400 shrink-0" />
            <button
                @click="navigate(item.uuid)"
                class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[150px] shrink-0"
                :class="{ 'font-semibold text-slate-900 dark:text-white': index === drive.breadcrumbs.length - 1 }"
            >
                {{ item.name }}
            </button>
        </template>
    </nav>
</template>
