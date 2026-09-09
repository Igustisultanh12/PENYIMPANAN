<script setup lang="ts">
import { ref } from 'vue';
import http from '@/utils/http';
import { useUiStore } from '@/stores/ui';
import type { FileItem, Folder } from '@/types';
import { X, Copy, Check, Lock, Calendar, Download, Users, Globe, Shield } from 'lucide-vue-next';

const props = defineProps<{
    item: FileItem | Folder | null;
    itemType: 'file' | 'folder';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const ui = useUiStore();

const accessType = ref<'public_link' | 'specific_user' | 'specific_whatsapp'>('public_link');
const permission = ref<'viewer' | 'commenter' | 'editor'>('viewer');
const targetContact = ref('');
const password = ref('');
const expiresAt = ref('');
const allowDownload = ref(true);

const generatedShareUrl = ref('');
const isCopied = ref(false);
const isLoading = ref(false);
const lookedUpUser = ref<{ name: string; avatar_url?: string } | null>(null);

async function checkWhatsAppUser() {
    if (!targetContact.value.trim()) return;
    try {
        const res = await http.post('/search/whatsapp-lookup', { phone: targetContact.value });
        lookedUpUser.value = res.data.data;
    } catch {
        lookedUpUser.value = null;
    }
}

async function createShareLink() {
    if (!props.item) return;

    isLoading.value = true;
    try {
        const res = await http.post('/shares', {
            item_type: props.itemType,
            item_uuid: props.item.uuid,
            permission: permission.value,
            access_type: accessType.value,
            target_contact: targetContact.value || null,
            password: password.value || null,
            expires_at: expiresAt.value || null,
            allow_download: allowDownload.value,
        });

        generatedShareUrl.value = res.data.data.share_url;
        ui.notify('Tautan berbagi berhasil dibuat.', 'success');
    } catch (error: any) {
        ui.notify(error.response?.data?.message || 'Gagal membuat tautan berbagi.', 'error');
    } finally {
        isLoading.value = false;
    }
}

function copyToClipboard() {
    if (!generatedShareUrl.value) return;
    navigator.clipboard.writeText(generatedShareUrl.value);
    isCopied.value = true;
    ui.notify('Tautan berhasil disalin ke papan klip!', 'info');
    setTimeout(() => { isCopied.value = false; }, 2500);
}
</script>

<template>
    <div
        v-if="item"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs transition-all"
    >
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-6 overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Bagikan Berkas</h3>
                    <p class="text-xs text-slate-400 truncate max-w-sm mt-0.5">
                        {{ 'original_name' in item ? item.original_name : item.name }}
                    </p>
                </div>
                <button
                    @click="emit('close')"
                    class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="py-4 space-y-4">
                <!-- Share Type Selector -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Metode Berbagi</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="accessType = 'public_link'"
                            class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl border text-xs font-medium transition-all"
                            :class="accessType === 'public_link'
                                ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-600 dark:text-blue-400 font-semibold'
                                : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <Globe class="w-3.5 h-3.5" />
                            Siapa saja dengan tautan
                        </button>
                        <button
                            type="button"
                            @click="accessType = 'specific_whatsapp'"
                            class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl border text-xs font-medium transition-all"
                            :class="accessType === 'specific_whatsapp'
                                ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-600 dark:text-blue-400 font-semibold'
                                : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <Users class="w-3.5 h-3.5" />
                            Target Pengguna / WA
                        </button>
                    </div>
                </div>

                <!-- Target Contact Lookup (If WhatsApp/User selected) -->
                <div v-if="accessType === 'specific_whatsapp'" class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Nomor WhatsApp atau Email</label>
                    <div class="flex gap-2">
                        <input
                            v-model="targetContact"
                            @blur="checkWhatsAppUser"
                            type="text"
                            placeholder="Contoh: 08123456789 atau user@domain.com"
                            class="flex-1 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-blue-500/20 outline-none"
                        />
                    </div>
                    <div v-if="lookedUpUser" class="flex items-center gap-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 text-emerald-800 dark:text-emerald-300 text-xs">
                        <Shield class="w-3.5 h-3.5 text-emerald-600" />
                        <span>Pengguna terverifikasi: <strong>{{ lookedUpUser.name }}</strong></span>
                    </div>
                </div>

                <!-- Permission Level -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Izin Akses</label>
                    <select
                        v-model="permission"
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-200 outline-none"
                    >
                        <option value="viewer">Viewer (Hanya Melihat / Mengunduh)</option>
                        <option value="commenter">Commenter (Bisa Komentar)</option>
                        <option value="editor">Editor (Bisa Mengubah & Menambah)</option>
                    </select>
                </div>

                <!-- Security Options: Password & Expiration -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1">
                            <Lock class="w-3.5 h-3.5 text-slate-400" />
                            Kata Sandi (Opsional)
                        </label>
                        <input
                            v-model="password"
                            type="password"
                            placeholder="Lindungi dengan sandi"
                            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1">
                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                            Kedaluwarsa (Opsional)
                        </label>
                        <input
                            v-model="expiresAt"
                            type="datetime-local"
                            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs outline-none"
                        />
                    </div>
                </div>

                <!-- Toggle Allow Download -->
                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer pt-1">
                    <input
                        v-model="allowDownload"
                        type="checkbox"
                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Izinkan penerima mengunduh berkas fisik</span>
                </label>

                <!-- Generated URL Box -->
                <div v-if="generatedShareUrl" class="p-3 bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700 rounded-xl space-y-2">
                    <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Tautan Siap Dibagikan</p>
                    <div class="flex items-center gap-2">
                        <input
                            :value="generatedShareUrl"
                            readonly
                            class="flex-1 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-700 dark:text-slate-300 outline-none"
                        />
                        <button
                            @click="copyToClipboard"
                            class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all"
                        >
                            <Check v-if="isCopied" class="w-3.5 h-3.5" />
                            <Copy v-else class="w-3.5 h-3.5" />
                            <span>{{ isCopied ? 'Tersalin' : 'Salin' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
                >
                    Tutup
                </button>
                <button
                    v-if="!generatedShareUrl"
                    @click="createShareLink"
                    :disabled="isLoading"
                    class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all disabled:opacity-50"
                >
                    {{ isLoading ? 'Memproses...' : 'Buat Tautan Berbagi' }}
                </button>
            </div>
        </div>
    </div>
</template>
