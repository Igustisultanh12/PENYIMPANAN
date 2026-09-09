<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { Cloud, Lock, Mail, ArrowRight } from 'lucide-vue-next';

const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

const email = ref('');
const password = ref('');
const isSubmitting = ref(false);

async function handleLogin() {
    isSubmitting.value = true;
    try {
        await auth.login({ email: email.value, password: password.value });
        ui.notify('Selamat datang kembali di MyStorage!', 'success');
        router.push('/drive');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Login gagal. Periksa kembali email dan password.', 'error');
    } finally {
        isSubmitting.value = false;
    }
}

function handleGoogleLogin() {
    window.location.href = '/api/v1/auth/google/redirect';
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-xl p-8">
            <!-- Brand Logo -->
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20 mx-auto mb-3">
                    <Cloud class="w-6 h-6" />
                </div>
                <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Masuk ke MyStorage</h2>
                <p class="text-xs text-slate-400 mt-1">Platform Cloud Storage Mandiri Anda</p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <Mail class="w-3.5 h-3.5 text-slate-400" />
                        Alamat Email
                    </label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        autofocus
                        placeholder="nama@email.com"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <Lock class="w-3.5 h-3.5 text-slate-400" />
                            Kata Sandi
                        </label>
                    </div>
                    <input
                        v-model="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    <span>{{ isSubmitting ? 'Memproses...' : 'Masuk Sekarang' }}</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-100 dark:border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="px-2 bg-white dark:bg-slate-900 text-slate-400">atau lanjutkan dengan</span>
                </div>
            </div>

            <!-- Google OAuth Button -->
            <button
                type="button"
                @click="handleGoogleLogin"
                class="w-full py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center justify-center gap-2 transition-all shadow-xs"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                </svg>
                <span>Masuk dengan Akun Google</span>
            </button>

            <!-- Register Link -->
            <p class="text-center text-xs text-slate-500 mt-6">
                Belum memiliki akun?
                <router-link to="/register" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                    Daftar sekarang
                </router-link>
            </p>
        </div>
    </div>
</template>
