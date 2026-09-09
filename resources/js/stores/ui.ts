import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { ToastMessage } from '@/types';

export const useUiStore = defineStore('ui', () => {
    const isDarkMode = ref(
        typeof window !== 'undefined' &&
        (localStorage.getItem('theme') === 'dark' ||
         (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches))
    );
    const isSidebarCollapsed = ref(false);
    const toasts = ref<ToastMessage[]>([]);

    function applyTheme(dark: boolean) {
        isDarkMode.value = dark;
        if (typeof document !== 'undefined') {
            if (dark) {
                document.documentElement.classList.add('dark');
                document.body?.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.body?.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        }
    }

    function toggleDarkMode() {
        applyTheme(!isDarkMode.value);
    }

    function initTheme() {
        const storedTheme = typeof localStorage !== 'undefined' ? localStorage.getItem('theme') : null;
        if (storedTheme === 'dark' || (!storedTheme && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            applyTheme(true);
        } else {
            applyTheme(false);
        }
    }

    function toggleSidebar() {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
    }

    function notify(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info', title?: string) {
        const id = Math.random().toString(36).substring(2, 9);
        toasts.value.push({ id, type, message, title });

        setTimeout(() => {
            removeToast(id);
        }, 4000);
    }

    function removeToast(id: string) {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }

    function addToast(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info', title?: string) {
        notify(message, type, title);
    }

    return {
        isDarkMode,
        isSidebarCollapsed,
        toasts,
        toggleDarkMode,
        initTheme,
        toggleSidebar,
        notify,
        addToast,
        removeToast,
    };
});
