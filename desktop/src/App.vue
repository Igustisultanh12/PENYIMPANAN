<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
    Cloud,
    Folder,
    RefreshCw,
    Pause,
    Play,
    AlertTriangle,
    CheckCircle2,
    Settings,
    HardDrive,
    Sliders,
    BatteryCharging,
    ExternalLink,
    ChevronRight,
    Laptop
} from 'lucide-vue-next';

interface SyncStatus {
    status: 'idle' | 'syncing' | 'paused' | 'offline';
    files_remaining: number;
    bytes_synced: number;
    total_bytes: number;
    last_synced_at: string | null;
    sync_dir: string;
}

interface Conflict {
    id: string;
    file_name: string;
    local_mtime: string;
    local_size: string;
    cloud_mtime: string;
    cloud_size: string;
}

const status = ref<SyncStatus>({
    status: 'idle',
    files_remaining: 0,
    bytes_synced: 0,
    total_bytes: 0,
    last_synced_at: new Date().toLocaleTimeString('id-ID'),
    sync_dir: 'C:\\Users\\User\\MyStorage',
});

const isPaused = ref(false);
const isSyncing = ref(false);
const activeTab = ref<'status' | 'selective' | 'settings'>('status');

// Conflicts demo state
const conflicts = ref<Conflict[]>([
    {
        id: 'conf-1',
        file_name: 'Laporan_Keuangan_Q3.xlsx',
        local_mtime: 'Hari ini, 15:45',
        local_size: '2.4 MB',
        cloud_mtime: 'Hari ini, 15:50',
        cloud_size: '2.6 MB'
    }
]);

// Selective sync folders
const selectiveFolders = ref([
    { id: 'f1', name: 'Dokumen Kantor', size: '1.2 GB', synced: true },
    { id: 'f2', name: 'Desain & Foto Produk', size: '4.8 GB', synced: false },
    { id: 'f3', name: 'Presentasi Proyek', size: '420 MB', synced: true },
    { id: 'f4', name: 'Arsip Backup', size: '12.5 GB', synced: false },
]);

// Settings
const pauseOnBattery = ref(true);
const pauseOnMetered = ref(true);
const maxUploadSpeed = ref(0); // 0 = unlimited
const maxDownloadSpeed = ref(0);

async function togglePause() {
    isPaused.value = !isPaused.value;
    status.value.status = isPaused.value ? 'paused' : 'idle';
}

async function triggerSync() {
    isSyncing.value = true;
    status.value.status = 'syncing';
    status.value.files_remaining = 3;

    setTimeout(() => {
        isSyncing.value = false;
        status.value.status = 'idle';
        status.value.files_remaining = 0;
        status.value.last_synced_at = new Date().toLocaleTimeString('id-ID');
    }, 2000);
}

function resolveConflict(id: string, strategy: 'keep_local' | 'keep_cloud' | 'keep_both') {
    conflicts.value = conflicts.value.filter(c => c.id !== id);
}

function openLocalFolder() {
    // In Tauri, invokes `open_local_folder`
    alert('Membuka folder lokal di file explorer: ' + status.value.sync_dir);
}
</script>

<template>
    <div class="h-screen w-screen flex flex-col bg-slate-50 font-sans text-slate-800 antialiased select-none">
        <!-- Window Titlebar / Navigation Header -->
        <header class="flex items-center justify-between px-5 py-3.5 bg-white border-b border-slate-200 shadow-xs shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-xs">
                    <Cloud class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-sm font-bold text-slate-900 leading-none">MyStorage Desktop</h1>
                    <p class="text-[11px] text-slate-400 mt-0.5">Klien Sinkronisasi PC v1.0.0</p>
                </div>
            </div>

            <!-- Tab Navigation Buttons -->
            <div class="flex items-center bg-slate-100 p-1 rounded-xl text-xs font-semibold">
                <button
                    @click="activeTab = 'status'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'status' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                >
                    Status
                </button>
                <button
                    @click="activeTab = 'selective'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'selective' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                >
                    Selective Sync
                </button>
                <button
                    @click="activeTab = 'settings'"
                    class="px-3 py-1.5 rounded-lg transition-all"
                    :class="activeTab === 'settings' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                >
                    Pengaturan
                </button>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 overflow-y-auto p-6 max-w-2xl mx-auto w-full">
            <!-- ================= TAB 1: STATUS ================= -->
            <div v-if="activeTab === 'status'" class="space-y-6">
                <!-- Status Hero Card -->
                <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm flex flex-col items-center text-center">
                    <div
                        class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4 transition-all"
                        :class="status.status === 'syncing'
                            ? 'bg-blue-50 text-blue-600 ring-4 ring-blue-50'
                            : status.status === 'paused'
                            ? 'bg-amber-50 text-amber-600'
                            : 'bg-emerald-50 text-emerald-600'"
                    >
                        <RefreshCw v-if="status.status === 'syncing'" class="w-8 h-8 animate-spin" />
                        <Pause v-else-if="status.status === 'paused'" class="w-8 h-8" />
                        <CheckCircle2 v-else class="w-8 h-8" />
                    </div>

                    <h2 class="text-base font-bold text-slate-900">
                        {{ status.status === 'syncing' ? 'Sedang Menyinkronkan Berkas...' : status.status === 'paused' ? 'Sinkronisasi Dijeda' : 'Semua Berkas Telah Disinkronkan' }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Terakhir diperbarui pada {{ status.last_synced_at }}
                    </p>

                    <!-- Control Buttons -->
                    <div class="flex items-center gap-3 mt-5">
                        <button
                            @click="triggerSync"
                            :disabled="isSyncing"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors disabled:opacity-50"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="isSyncing ? 'animate-spin' : ''" />
                            <span>Sinkron Sekarang</span>
                        </button>

                        <button
                            @click="togglePause"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors"
                        >
                            <Play v-if="isPaused" class="w-3.5 h-3.5 text-emerald-600 fill-emerald-600" />
                            <Pause v-else class="w-3.5 h-3.5 text-slate-500" />
                            <span>{{ isPaused ? 'Lanjutkan' : 'Jeda' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Local Directory Shortcut Card -->
                <div class="p-4 rounded-2xl bg-white border border-slate-200 flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-slate-100 text-slate-600">
                            <Folder class="w-5 h-5 text-blue-500" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800">Folder MyStorage Lokal</p>
                            <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ status.sync_dir }}</p>
                        </div>
                    </div>
                    <button
                        @click="openLocalFolder"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors"
                    >
                        <span>Buka Folder</span>
                        <ExternalLink class="w-3.5 h-3.5 text-slate-400" />
                    </button>
                </div>

                <!-- Conflicts Alert Section -->
                <div v-if="conflicts.length > 0" class="space-y-3">
                    <div class="flex items-center gap-2 text-amber-700">
                        <AlertTriangle class="w-4 h-4 text-amber-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider">Konflik Sinkronisasi Terdeteksi ({{ conflicts.length }})</h3>
                    </div>

                    <div v-for="conf in conflicts" :key="conf.id" class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200 space-y-3">
                        <p class="text-xs font-bold text-amber-900">{{ conf.file_name }}</p>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            Berkas ini telah diubah di komputer Anda dan juga di cloud secara bersamaan. Silakan pilih versi yang ingin dipertahankan:
                        </p>

                        <!-- Side by Side Diff Preview -->
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-white rounded-xl border border-amber-200/80">
                                <span class="font-bold text-slate-700">Versi Komputer Anda</span>
                                <p class="text-[11px] text-slate-400 mt-1">Ukuran: {{ conf.local_size }}</p>
                                <p class="text-[11px] text-slate-400">Diubah: {{ conf.local_mtime }}</p>
                            </div>
                            <div class="p-3 bg-white rounded-xl border border-amber-200/80">
                                <span class="font-bold text-blue-600">Versi Cloud MyStorage</span>
                                <p class="text-[11px] text-slate-400 mt-1">Ukuran: {{ conf.cloud_size }}</p>
                                <p class="text-[11px] text-slate-400">Diubah: {{ conf.cloud_mtime }}</p>
                            </div>
                        </div>

                        <!-- 3-Way Choice Actions -->
                        <div class="flex items-center gap-2 pt-1">
                            <button
                                @click="resolveConflict(conf.id, 'keep_local')"
                                class="flex-1 px-3 py-1.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors"
                            >
                                Simpan Versi PC
                            </button>
                            <button
                                @click="resolveConflict(conf.id, 'keep_cloud')"
                                class="flex-1 px-3 py-1.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-blue-600 transition-colors"
                            >
                                Simpan Versi Cloud
                            </button>
                            <button
                                @click="resolveConflict(conf.id, 'keep_both')"
                                class="flex-1 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white transition-colors"
                            >
                                Simpan Keduanya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 2: SELECTIVE SYNC ================= -->
            <div v-else-if="activeTab === 'selective'" class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Sinkronisasi Selektif (Selective Sync)</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Pilih folder mana saja dari cloud yang ingin diunduh dan disimpan di hardisk komputer ini untuk menghemat ruang penyimpanan PC Anda.
                    </p>
                </div>

                <div class="divide-y divide-slate-100 bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                    <label
                        v-for="f in selectiveFolders"
                        :key="f.id"
                        class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50/60 cursor-pointer transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                v-model="f.synced"
                                class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500"
                            />
                            <div>
                                <p class="text-xs font-bold text-slate-800">{{ f.name }}</p>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ f.size }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-medium" :class="f.synced ? 'text-emerald-600' : 'text-slate-400'">
                            {{ f.synced ? 'Tersinkron di PC' : 'Hanya di Cloud' }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- ================= TAB 3: SETTINGS ================= -->
            <div v-else class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Pengaturan Jaringan & Perangkat</h3>
                    <p class="text-xs text-slate-500 mt-1">Sesuaikan kinerja sinkronisasi desktop dengan daya dan kuota internet Anda.</p>
                </div>

                <div class="space-y-3">
                    <label class="p-4 rounded-2xl bg-white border border-slate-200 flex items-center justify-between cursor-pointer shadow-xs">
                        <div class="flex items-center gap-3">
                            <BatteryCharging class="w-5 h-5 text-amber-500" />
                            <div>
                                <p class="text-xs font-bold text-slate-800">Hemat Daya Baterai</p>
                                <p class="text-[11px] text-slate-400">Jeda sinkronisasi otomatis ketika laptop menggunakan daya baterai.</p>
                            </div>
                        </div>
                        <input type="checkbox" v-model="pauseOnBattery" class="w-4 h-4 rounded text-blue-600" />
                    </label>

                    <label class="p-4 rounded-2xl bg-white border border-slate-200 flex items-center justify-between cursor-pointer shadow-xs">
                        <div class="flex items-center gap-3">
                            <Sliders class="w-5 h-5 text-blue-500" />
                            <div>
                                <p class="text-xs font-bold text-slate-800">Koneksi Bertarif (Metered Connection)</p>
                                <p class="text-[11px] text-slate-400">Jeda upload/download otomatis saat terhubung ke hotspot kuota seluler.</p>
                            </div>
                        </div>
                        <input type="checkbox" v-model="pauseOnMetered" class="w-4 h-4 rounded text-blue-600" />
                    </label>
                </div>
            </div>
        </main>
    </div>
</template>
