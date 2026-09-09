<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useUploadStore } from '@/stores/upload';
import { useDriveStore } from '@/stores/drive';
import { useI18n } from '@/composables/useI18n';
import {
    Menu,
    Search,
    Sun,
    Moon,
    Upload,
    FolderPlus,
    Bell,
    LogOut,
    User as UserIcon,
    Shield,
    ChevronDown,
    X,
} from 'lucide-vue-next';

const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const upload = useUploadStore();
const drive = useDriveStore();
const { t, locale, setLocale } = useI18n();

const searchQuery = ref('');
const showProfileMenu = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function triggerFileInput() {
    fileInput.value?.click();
}

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        upload.addFilesToQueue(target.files, drive.currentFolderUuid);
        target.value = ''; // Reset input
    }
}

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.push({ path: '/drive', query: { q: searchQuery.value.trim() } });
    }
}

function clearSearch() {
    searchQuery.value = '';
    router.push({ path: '/drive' });
}
</script>

<template>
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-4 shrink-0 z-20">
        <!-- Left: Menu Toggle + Search Bar -->
        <div class="flex items-center gap-3 flex-1 max-w-2xl">
            <button
                @click="ui.toggleSidebar"
                class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Toggle Sidebar"
            >
                <Menu class="w-5 h-5" />
            </button>

            <!-- Global Search Bar -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <Search class="w-4 h-4" />
                </div>
                <input
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    type="text"
                    :placeholder="t('search.placeholder')"
                    class="w-full pl-10 pr-10 py-2 rounded-xl bg-slate-100/80 dark:bg-slate-800/80 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500/20 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 outline-none transition-all"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Hidden input for file uploads -->
        <input
            ref="fileInput"
            type="file"
            multiple
            class="hidden"
            @change="handleFileChange"
        />

        <!-- Right: Actions, Language, Theme, User -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Upload Button -->
            <button
                @click="triggerFileInput"
                class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs sm:text-sm font-semibold shadow-sm shadow-blue-500/20 transition-all hover:shadow"
            >
                <Upload class="w-4 h-4" />
                <span class="hidden sm:inline">{{ t('action.upload') }}</span>
            </button>

            <!-- Theme Toggle -->
            <button
                @click="ui.toggleDarkMode"
                class="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Toggle Dark Mode"
            >
                <Sun v-if="ui.isDarkMode" class="w-4 h-4 text-amber-400" />
                <Moon v-else class="w-4 h-4 text-slate-600" />
            </button>

            <!-- Locale Toggle -->
            <button
                @click="setLocale(locale === 'id' ? 'en' : 'id')"
                class="px-2 py-1 rounded-lg text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            >
                {{ locale }}
            </button>

            <!-- User Menu -->
            <div class="relative">
                <button
                    @click="showProfileMenu = !showProfileMenu"
                    class="flex items-center gap-2 p-1 pl-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                >
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                        <img
                            v-if="auth.user?.avatar_url"
                            :src="auth.user.avatar_url"
                            class="w-full h-full rounded-full object-cover"
                        />
                        <span v-else>{{ auth.user?.name ? auth.user.name.charAt(0) : 'U' }}</span>
                    </div>
                    <ChevronDown class="w-3.5 h-3.5 text-slate-400 mr-1" />
                </button>

                <!-- Dropdown -->
                <div
                    v-if="showProfileMenu"
                    @click="showProfileMenu = false"
                    class="fixed inset-0 z-30"
                ></div>

                <div
                    v-if="showProfileMenu"
                    class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-2 z-40"
                >
                    <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth.user?.email }}</p>
                    </div>

                    <router-link
                        to="/security"
                        class="flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    >
                        <Shield class="w-4 h-4 text-slate-400" />
                        Pusat Keamanan
                    </router-link>

                    <router-link
                        to="/settings"
                        class="flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    >
                        <UserIcon class="w-4 h-4 text-slate-400" />
                        Pengaturan Profil
                    </router-link>

                    <div class="border-t border-slate-100 dark:border-slate-800 mt-1 pt-1">
                        <button
                            @click="auth.logout"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors text-left font-medium"
                        >
                            <LogOut class="w-4 h-4" />
                            Keluar Akun
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
