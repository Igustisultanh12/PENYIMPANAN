<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { Cloud, Lock, Mail, User, Smartphone, ArrowRight, ArrowLeft } from 'lucide-vue-next';
import ThemeToggle from '@/components/ThemeToggle.vue';

const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

const name = ref('');
const email = ref('');
const whatsapp = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const terms = ref(false);
const isSubmitting = ref(false);

async function handleRegister() {
    if (!terms.value) {
        ui.notify('Anda harus menyetujui Ketentuan Layanan & Kebijakan Privasi.', 'warning');
        return;
    }

    isSubmitting.value = true;
    try {
        await auth.register({
            name: name.value,
            email: email.value,
            whatsapp: whatsapp.value || undefined,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            terms: terms.value,
        });

        ui.notify('Pendaftaran berhasil! Silakan periksa email Anda untuk verifikasi akun.', 'success');
        router.push('/login');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal mendaftar. Periksa kembali form input Anda.', 'error');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950 relative overflow-hidden py-12">
        <!-- Ambient Glowing Background Blobs -->
        <div class="absolute -top-20 -left-20 w-80 h-80 bg-blue-500/15 dark:bg-blue-600/20 rounded-full blur-3xl pointer-events-none animate-float-slow"></div>
        <div class="absolute -bottom-20 -right-20 w-96 h-96 bg-indigo-500/15 dark:bg-indigo-600/20 rounded-full blur-3xl pointer-events-none animate-float-reverse"></div>

        <!-- Top Controls: Back to Home + Theme Toggle -->
        <div class="absolute top-5 inset-x-5 max-w-4xl mx-auto flex items-center justify-between z-10">
            <router-link
                to="/"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border border-slate-200/60 dark:border-slate-800 transition-all hover:-translate-x-1"
            >
                <ArrowLeft class="w-3.5 h-3.5" />
                <span>Beranda</span>
            </router-link>

            <ThemeToggle variant="icon" />
        </div>

        <div class="w-full max-w-md bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-2xl p-8 relative z-10 animate-modal-pop">
            <!-- Brand Logo -->
            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/25 mx-auto mb-3 transition-transform duration-300 hover:rotate-6 hover:scale-105">
                    <Cloud class="w-6 h-6" />
                </div>
                <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Daftar Akun MyStorage</h2>
                <p class="text-xs text-slate-400 mt-1">Mulai simpan file Anda dengan aman</p>
            </div>

            <!-- Register Form -->
            <form @submit.prevent="handleRegister" class="space-y-3.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                        <User class="w-3.5 h-3.5 text-slate-400" />
                        Nama Lengkap
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        autofocus
                        placeholder="Nama Lengkap Anda"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                        <Mail class="w-3.5 h-3.5 text-slate-400" />
                        Alamat Email
                    </label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="nama@email.com"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                        <Smartphone class="w-3.5 h-3.5 text-slate-400" />
                        Nomor WhatsApp
                    </label>
                    <input
                        v-model="whatsapp"
                        type="text"
                        placeholder="Contoh: 08123456789"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                            <Lock class="w-3.5 h-3.5 text-slate-400" />
                            Kata Sandi
                        </label>
                        <input
                            v-model="password"
                            type="password"
                            required
                            placeholder="Min. 8 karakter"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                            <Lock class="w-3.5 h-3.5 text-slate-400" />
                            Konfirmasi
                        </label>
                        <input
                            v-model="passwordConfirmation"
                            type="password"
                            required
                            placeholder="Ulangi sandi"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                </div>

                <!-- Terms Checkbox -->
                <div class="pt-1">
                    <label class="flex items-start gap-2 cursor-pointer text-xs text-slate-600 dark:text-slate-400">
                        <input
                            v-model="terms"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span>Saya menyetujui Ketentuan Layanan dan Kebijakan Privasi MyStorage.</span>
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="isSubmitting || !terms"
                    class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50 mt-2"
                >
                    <span>{{ isSubmitting ? 'Mendaftarkan...' : 'Daftar Akun Baru' }}</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </button>
            </form>

            <!-- Login Link -->
            <p class="text-center text-xs text-slate-500 mt-6">
                Sudah memiliki akun?
                <router-link to="/login" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                    Masuk di sini
                </router-link>
            </p>
        </div>
    </div>
</template>
