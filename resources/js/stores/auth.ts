import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import http from '@/utils/http';
import type { User } from '@/types';
import { useUiStore } from './ui';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(localStorage.getItem('mystorage_token'));
    const isLoading = ref(false);

    const isAuthenticated = computed(() => !!token.value);
    const isEmailVerified = computed(() => user.value?.email_verified ?? false);

    async function login(credentials: { email: string; password: string }) {
        isLoading.value = true;
        try {
            const res = await http.post('/auth/login', credentials);
            token.value = res.data.data.token;
            user.value = res.data.data.user;
            localStorage.setItem('mystorage_token', token.value as string);
            return res.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function register(payload: {
        name: string;
        email: string;
        whatsapp?: string;
        password: string;
        password_confirmation: string;
        terms: boolean;
    }) {
        isLoading.value = true;
        try {
            const res = await http.post('/auth/register', payload);
            return res.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchUser() {
        if (!token.value) return;
        isLoading.value = true;
        try {
            const res = await http.get('/me');
            user.value = res.data.data;
        } catch (err) {
            token.value = null;
            user.value = null;
            localStorage.removeItem('mystorage_token');
        } finally {
            isLoading.value = false;
        }
    }

    async function logout() {
        const ui = useUiStore();
        try {
            await http.post('/auth/logout');
        } catch {
            // Ignore error
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('mystorage_token');
            ui.notify('Anda telah berhasil logout.', 'info');
            window.location.href = '/login';
        }
    }

    return {
        user,
        token,
        isLoading,
        isAuthenticated,
        isEmailVerified,
        login,
        register,
        fetchUser,
        logout,
    };
});
