<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '@/utils/http';
import { useUiStore } from '@/stores/ui';
import { CheckCircle2, AlertCircle, Mail, ArrowRight, Loader2 } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();

const status = ref<'loading' | 'success' | 'expired' | 'resend'>('loading');
const resendEmail = ref('');
const isResending = ref(false);

async function verifyToken(token: string) {
    status.value = 'loading';
    try {
        await http.get(`/auth/verify-email/${token}`);
        status.value = 'success';
        ui.notify('Email Anda berhasil diverifikasi!', 'success');
    } catch {
        status.value = 'expired';
    }
}

async function handleResend() {
    if (!resendEmail.value.trim()) return;

    isResending.value = true;
    try {
        const res = await http.post('/auth/resend-verification', { email: resendEmail.value });
        ui.notify(res.data.message || 'Tautan verifikasi baru telah dikirimkan.', 'success');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal mengirim ulang verifikasi.', 'error');
    } finally {
        isResending.value = false;
    }
}

onMounted(() => {
    const token = route.params.token as string;
    if (token) {
        verifyToken(token);
    } else {
        status.value = 'resend';
    }
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-xl p-8 text-center">
            <!-- Loading State -->
            <div v-if="status === 'loading'" class="py-8">
                <Loader2 class="w-12 h-12 text-blue-600 animate-spin mx-auto mb-4" />
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Memverifikasi Alamat Email...</h3>
                <p class="text-xs text-slate-400 mt-1">Harap tunggu sebentar, sistem sedang memvalidasi token Anda.</p>
            </div>

            <!-- Success State -->
            <div v-else-if="status === 'success'" class="py-6">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 flex items-center justify-center mx-auto mb-4 border border-emerald-200 dark:border-emerald-800">
                    <CheckCircle2 class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Email Berhasil Diverifikasi!</h3>
                <p class="text-xs text-slate-500 mt-1 mb-6">
                    Akun MyStorage Anda kini berstatus aktif. Anda dapat langsung mengunggah dan mengelola berkas.
                </p>
                <router-link
                    to="/login"
                    class="inline-flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20"
                >
                    <span>Masuk ke Dashboard</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </router-link>
            </div>

            <!-- Expired / Invalid State -->
            <div v-else-if="status === 'expired' || status === 'resend'" class="py-6">
                <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center mx-auto mb-4 border border-amber-200 dark:border-amber-800">
                    <AlertCircle class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                    {{ status === 'expired' ? 'Tautan Kedaluwarsa' : 'Verifikasi Email Diperlukan' }}
                </h3>
                <p class="text-xs text-slate-500 mt-1 mb-6">
                    Tautan verifikasi email Anda tidak valid atau telah melewati batas 24 jam. Silakan minta tautan baru di bawah ini.
                </p>

                <form @submit.prevent="handleResend" class="space-y-3">
                    <input
                        v-model="resendEmail"
                        type="email"
                        required
                        placeholder="Ketik email Anda yang terdaftar"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                    <button
                        type="submit"
                        :disabled="isResending"
                        class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-all disabled:opacity-50"
                    >
                        {{ isResending ? 'Mengirim Ulang...' : 'Kirim Ulang Email Verifikasi' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
