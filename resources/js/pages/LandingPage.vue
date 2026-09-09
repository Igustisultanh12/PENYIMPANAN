<script setup lang="ts">
import { Cloud, Shield, Zap, Share2, Smartphone, Terminal, ArrowRight, Lock, CheckCircle2 } from 'lucide-vue-next';
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<template>
    <div class="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col selection:bg-blue-600 selection:text-white">
        <!-- Top Navbar -->
        <header class="h-20 border-b border-slate-100 dark:border-slate-800/80 px-6 sm:px-12 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                    <Cloud class="w-6 h-6" />
                </div>
                <span class="text-xl font-extrabold tracking-tight">MyStorage</span>
            </div>

            <div class="flex items-center gap-3">
                <router-link
                    to="/login"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    Masuk
                </router-link>
                <router-link
                    to="/register"
                    class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-blue-500/20 transition-all hover:shadow"
                >
                    Mulai Gratis
                </router-link>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20 px-6 text-center max-w-4xl mx-auto flex-1 flex flex-col items-center justify-center">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-600 dark:text-blue-400 text-xs font-semibold mb-6">
                <Shield class="w-3.5 h-3.5" />
                <span>Platform Cloud Storage Mandiri Berperforma Tinggi</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-6 leading-tight">
                Your files. Secure. Organized. <br class="hidden sm:inline" />
                <span class="bg-gradient-to-r from-blue-600 to-indigo-500 bg-clip-text text-transparent">Accessible anywhere.</span>
            </h1>

            <p class="text-sm sm:text-base text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                Kelola dokumen, foto, video, dan arsip penting Anda dengan sistem keamanan berlapis, upload chunk resumable, tautan berbagi terlindungi sandi, dan penyimpanan terisolasi.
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                <router-link
                    to="/register"
                    class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-bold shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2 transition-all hover:scale-105"
                >
                    <span>Mulai Menggunakan MyStorage</span>
                    <ArrowRight class="w-4 h-4" />
                </router-link>
                <router-link
                    to="/login"
                    class="w-full sm:w-auto px-8 py-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900 text-sm font-semibold transition-all text-slate-700 dark:text-slate-300"
                >
                    Buka Drive Saya
                </router-link>
            </div>
        </section>

        <!-- Feature Pillars -->
        <section class="py-16 px-6 sm:px-12 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-800/80">
            <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Pillar 1 -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 flex items-center justify-center">
                        <Lock class="w-6 h-6" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Penyimpanan Terisolasi</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Struktur penyimpanan berbasis UUID dengan isolasi tenant dan validasi magic bytes mencegah eksekusi kode berbahaya.
                    </p>
                </div>

                <!-- Pillar 2 -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 flex items-center justify-center">
                        <Zap class="w-6 h-6" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Resumable Chunk Upload</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Unggah berkas ukuran besar tanpa batas RAM PHP. Jika koneksi terputus, upload langsung berlanjut dari chunk terakhir.
                    </p>
                </div>

                <!-- Pillar 3 -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 flex items-center justify-center">
                        <Share2 class="w-6 h-6" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Kontrol Berbagi Fleksibel</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Bagikan berkas dengan tautan berbatas waktu, proteksi kata sandi, serta integrasi pencarian pengguna via WhatsApp aman.
                    </p>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 px-6 text-center text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800">
            <p>&copy; 2026 MyStorage Platform. Semua hak cipta dilindungi undang-undang.</p>
        </footer>
    </div>
</template>
