<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import http from '@/utils/http';
import type { StorageStats } from '@/types';
import {
    HardDrive,
    Clock,
    Star,
    Users,
    Trash2,
    Database,
    ShieldCheck,
    Settings,
    Cloud,
    FolderPlus,
    UploadCloud,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const { t } = useI18n();

const storageStats = ref<StorageStats | null>(null);

async function loadStorage() {
    try {
        const res = await http.get('/storage/stats');
        storageStats.value = res.data.data;
    } catch {
        // Fallback to basic auth storage
    }
}

onMounted(() => {
    loadStorage();
});

const navItems = computed(() => [
    { name: t('nav.drive'), path: '/drive', icon: HardDrive },
    { name: t('nav.recent'), path: '/recent', icon: Clock },
    { name: t('nav.starred'), path: '/starred', icon: Star },
    { name: t('nav.shared'), path: '/shared', icon: Users },
    { name: t('nav.trash'), path: '/trash', icon: Trash2 },
    { name: t('nav.storage'), path: '/storage', icon: Database },
    { name: t('nav.security'), path: '/security', icon: ShieldCheck },
    { name: t('nav.settings'), path: '/settings', icon: Settings },
]);

const usedFormatted = computed(() => storageStats.value?.human_readable?.used || '0 B');
const quotaFormatted = computed(() => storageStats.value?.human_readable?.quota || '10 GB');
const percentage = computed(() => storageStats.value?.percentage || 0);

const statusColor = computed(() => {
    const p = percentage.value;
    if (p >= 90) return 'bg-rose-500';
    if (p >= 80) return 'bg-amber-500';
    return 'bg-blue-600';
});
</script>

<template>
    <aside
        class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col shrink-0 select-none transition-all duration-200"
        :class="{ '-ml-64 sm:ml-0': ui.isSidebarCollapsed }"
    >
        <!-- Brand Header -->
        <div class="h-16 flex items-center px-6 gap-3 border-b border-slate-100 dark:border-slate-800/80">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                <Cloud class="w-5 h-5" />
            </div>
            <div>
                <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">MyStorage</span>
                <span class="block text-[10px] uppercase font-semibold text-blue-600 dark:text-blue-400 tracking-wider">Cloud Platform</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <router-link
                v-for="item in navItems"
                :key="item.path"
                :to="item.path"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all group"
                :class="route.path.startsWith(item.path)
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-semibold'
                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'"
            >
                <component
                    :is="item.icon"
                    class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110"
                    :class="route.path.startsWith(item.path) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500'"
                />
                <span>{{ item.name }}</span>
            </router-link>
        </nav>

        <!-- Storage Quota Widget at Bottom -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    <span class="flex items-center gap-1.5">
                        <Database class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                        Penyimpanan
                    </span>
                    <span class="text-[11px] font-mono">{{ percentage }}%</span>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                    <div
                        class="h-full transition-all duration-500"
                        :class="statusColor"
                        :style="{ width: `${Math.min(100, percentage)}%` }"
                    ></div>
                </div>

                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">
                    {{ usedFormatted }} {{ t('storage.used') }} {{ quotaFormatted }}
                </p>
            </div>
        </div>
    </aside>
</template>
