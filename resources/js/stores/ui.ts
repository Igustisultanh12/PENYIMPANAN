import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { ToastMessage } from '@/types';

export const useUiStore = defineStore('ui', () => {
    const isDarkMode = ref(localStorage.getItem('theme') === 'dark');
    const isSidebarCollapsed = ref(false);
    const toasts = ref<ToastMessage[]>([]);

    function toggleDarkMode() {
        isDarkMode.value = !isDarkMode.value;
        if (isDarkMode.value) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    }

    function initTheme() {
        if (isDarkMode.value || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            isDarkMode.value = true;
        } else {
            document.documentElement.classList.remove('dark');
            isDarkMode.value = false;
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

    return {
        isDarkMode,
        isSidebarCollapsed,
        toasts,
        toggleDarkMode,
        initTheme,
        toggleSidebar,
        notify,
        removeToast,
    };
});
