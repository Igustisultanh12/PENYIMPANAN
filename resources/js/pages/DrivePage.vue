<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useDriveStore } from '@/stores/drive';
import { useUploadStore } from '@/stores/upload';
import { useI18n } from '@/composables/useI18n';
import type { FileItem, Folder } from '@/types';
import Breadcrumb from '@/components/Breadcrumb.vue';
import FolderCard from '@/components/FolderCard.vue';
import FileCard from '@/components/FileCard.vue';
import FileTable from '@/components/FileTable.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import ShareModal from '@/components/ShareModal.vue';
import CreateFolderModal from '@/components/CreateFolderModal.vue';
import RenameModal from '@/components/RenameModal.vue';
import MoveItemModal from '@/components/MoveItemModal.vue';
import OfficeEditorModal from '@/components/OfficeEditorModal.vue';
import CreateDocumentModal from '@/components/CreateDocumentModal.vue';
import {
    LayoutGrid,
    List,
    FolderPlus,
    UploadCloud,
    Folder as FolderIcon,
    FileUp,
    Inbox,
    FileText,
    ChevronDown,
    Sheet,
    Presentation,
} from 'lucide-vue-next';

const route = useRoute();
const drive = useDriveStore();
const upload = useUploadStore();
const { t } = useI18n();

// Modals state
const isCreateFolderOpen = ref(false);
const isCreateDocOpen = ref(false);
const defaultDocType = ref<'document' | 'spreadsheet' | 'presentation'>('document');
const isOfficeModalOpen = ref(false);
const activeOfficeFile = ref<FileItem | null>(null);
const isDocDropdownOpen = ref(false);

const activePreviewFile = ref<FileItem | null>(null);
const activeShareItem = ref<FileItem | Folder | null>(null);
const activeShareType = ref<'file' | 'folder'>('file');
const activeRenameItem = ref<FileItem | Folder | null>(null);
const activeMoveItem = ref<FileItem | Folder | null>(null);
const activeMoveType = ref<'file' | 'folder'>('file');

// Drag & drop state
const isDraggingOver = ref(false);

const categories = [
    { id: null, label: 'filter.all' },
    { id: 'image', label: 'filter.images' },
    { id: 'document', label: 'filter.documents' },
    { id: 'video', label: 'filter.videos' },
    { id: 'audio', label: 'filter.audio' },
    { id: 'archive', label: 'filter.archives' },
];

function loadCurrentFolder() {
    const folderUuid = (route.query.folder as string) || null;
    drive.fetchDrive(folderUuid, 'drive');
}

onMounted(() => {
    loadCurrentFolder();
});

watch(() => route.query.folder, () => {
    loadCurrentFolder();
});

function handleDrop(e: DragEvent) {
    isDraggingOver.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        upload.addFilesToQueue(e.dataTransfer.files, drive.currentFolderUuid);
    }
}

function openShare(item: FileItem | Folder, type: 'file' | 'folder') {
    activeShareItem.value = item;
    activeShareType.value = type;
}

function openRename(item: FileItem | Folder) {
    activeRenameItem.value = item;
}

function handleRenameSave(newName: string) {
    if (!activeRenameItem.value) return;
    if ('original_name' in activeRenameItem.value) {
        drive.renameFile(activeRenameItem.value.uuid, newName);
    } else {
        drive.renameFolder(activeRenameItem.value.uuid, newName);
    }
}

function openMove(item: FileItem | Folder, type: 'file' | 'folder') {
    activeMoveItem.value = item;
    activeMoveType.value = type;
}

function handleDownload(file: FileItem) {
    window.open(`/api/v1/files/${file.uuid}/preview`, '_blank');
}

function openCreateDoc(type: 'document' | 'spreadsheet' | 'presentation') {
    defaultDocType.value = type;
    isDocDropdownOpen.value = false;
    isCreateDocOpen.value = true;
}

function handleFilePreview(file: FileItem) {
    const ext = (file.extension || '').toLowerCase();
    if (['docx', 'doc', 'xlsx', 'xls', 'csv', 'tsv', 'ods', 'pptx', 'ppt', 'txt', 'md', 'log'].includes(ext)) {
        activeOfficeFile.value = file;
        isOfficeModalOpen.value = true;
    } else {
        activePreviewFile.value = file;
    }
}

function handleDocCreated(file: FileItem) {
    drive.files.unshift(file);
    activeOfficeFile.value = file;
    isOfficeModalOpen.value = true;
}

function handleOfficeSaved(file: FileItem) {
    const idx = drive.files.findIndex(f => f.uuid === file.uuid);
    if (idx !== -1) {
        drive.files[idx] = file;
    }
}
</script>

<template>
    <div
        class="h-full flex flex-col p-4 sm:p-6 overflow-y-auto relative"
        @dragover.prevent="isDraggingOver = true"
        @dragleave.prevent="isDraggingOver = false"
        @drop.prevent="handleDrop"
    >
        <!-- Full Page Drag & Drop Overlay -->
        <div
            v-if="isDraggingOver"
            class="absolute inset-4 sm:inset-6 z-40 rounded-3xl border-2 border-dashed border-blue-500 bg-blue-50/90 dark:bg-blue-950/80 backdrop-blur-xs flex flex-col items-center justify-center pointer-events-none transition-all"
        >
            <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center shadow-xl animate-bounce mb-4">
                <FileUp class="w-8 h-8" />
            </div>
            <p class="text-base font-bold text-blue-600 dark:text-blue-400">Lepaskan berkas untuk mengunggah</p>
            <p class="text-xs text-slate-500 mt-1">Chunk upload otomatis akan memproses berkas Anda</p>
        </div>

        <!-- Top Action Bar & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <Breadcrumb />

            <div class="flex items-center gap-2 self-end sm:self-auto">
                <button
                    @click="isCreateFolderOpen = true"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
                >
                    <FolderPlus class="w-3.5 h-3.5 text-blue-500" />
                    <span>{{ t('action.new_folder') }}</span>
                </button>

                <!-- New Office Document Dropdown -->
                <div class="relative">
                    <button
                        @click="isDocDropdownOpen = !isDocDropdownOpen"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition-colors shadow-xs"
                    >
                        <FileText class="w-3.5 h-3.5" />
                        <span>Dokumen Baru</span>
                        <ChevronDown class="w-3 h-3 ml-0.5" />
                    </button>

                    <div
                        v-if="isDocDropdownOpen"
                        class="absolute right-0 sm:left-0 sm:right-auto mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl py-1.5 z-30 animate-in fade-in zoom-in-95 duration-100"
                    >
                        <button
                            @click="openCreateDoc('document')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
                        >
                            <FileText class="w-4 h-4 text-blue-500" />
                            <span>Dokumen Teks (.docx)</span>
                        </button>
                        <button
                            @click="openCreateDoc('spreadsheet')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
                        >
                            <Sheet class="w-4 h-4 text-emerald-500" />
                            <span>Lembar Sebar (.xlsx)</span>
                        </button>
                        <button
                            @click="openCreateDoc('presentation')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-left"
                        >
                            <Presentation class="w-4 h-4 text-amber-500" />
                            <span>Presentasi Slide (.pptx)</span>
                        </button>
                    </div>
                </div>

                <!-- View Mode Toggle -->
                <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                    <button
                        @click="drive.viewMode = 'grid'"
                        class="p-1 rounded-lg transition-colors"
                        :class="drive.viewMode === 'grid' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-400 hover:text-slate-600'"
                    >
                        <LayoutGrid class="w-3.5 h-3.5" />
                    </button>
                    <button
                        @click="drive.viewMode = 'list'"
                        class="p-1 rounded-lg transition-colors"
                        :class="drive.viewMode === 'list' ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs' : 'text-slate-400 hover:text-slate-600'"
                    >
                        <List class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Category Filter Pills -->
        <div class="flex items-center gap-2 overflow-x-auto py-3 shrink-0">
            <button
                v-for="cat in categories"
                :key="cat.label"
                @click="drive.currentCategory = cat.id; loadCurrentFolder()"
                class="px-3 py-1 rounded-full text-xs font-medium transition-all shrink-0"
                :class="drive.currentCategory === cat.id
                    ? 'bg-blue-600 text-white font-semibold shadow-xs'
                    : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
            >
                {{ t(cat.label) }}
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto space-y-6 pt-2">
            <!-- Folders Section (Only shown if folders exist) -->
            <div v-if="drive.folders.length > 0">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Folder</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <FolderCard
                        v-for="folder in drive.folders"
                        :key="folder.uuid"
                        :folder="folder"
                        @rename="openRename"
                        @move="f => openMove(f, 'folder')"
                        @share="f => openShare(f, 'folder')"
                        @delete="f => drive.deleteFolder(f.uuid)"
                    />
                </div>
            </div>

            <!-- Files Section -->
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                    Berkas ({{ drive.files.length }})
                </p>

                <!-- Grid View -->
                <div
                    v-if="drive.viewMode === 'grid' && drive.files.length > 0"
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3.5"
                >
                    <FileCard
                        v-for="file in drive.files"
                        :key="file.uuid"
                        :file="file"
                        @preview="handleFilePreview"
                        @download="handleDownload"
                        @share="f => openShare(f, 'file')"
                        @star="f => drive.toggleStarFile(f)"
                        @rename="openRename"
                        @move="f => openMove(f, 'file')"
                        @delete="f => drive.trashFile(f.uuid)"
                    />
                </div>

                <!-- Table View -->
                <FileTable
                    v-else-if="drive.viewMode === 'list' && drive.files.length > 0"
                    :files="drive.files"
                    @preview="handleFilePreview"
                    @download="handleDownload"
                    @share="f => openShare(f, 'file')"
                    @star="f => drive.toggleStarFile(f)"
                    @rename="openRename"
                    @move="f => openMove(f, 'file')"
                    @delete="f => drive.trashFile(f.uuid)"
                />

                <!-- Empty State -->
                <div
                    v-else-if="drive.folders.length === 0 && drive.files.length === 0 && !drive.isLoading"
                    class="py-16 text-center max-w-sm mx-auto flex flex-col items-center"
                >
                    <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-4">
                        <Inbox class="w-8 h-8 stroke-1" />
                    </div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">
                        {{ t('empty.no_files') }}
                    </h4>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-6 leading-relaxed">
                        {{ t('empty.upload_hint') }}
                    </p>
                    <button
                        @click="isCreateFolderOpen = true"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
                    >
                        {{ t('action.new_folder') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <CreateFolderModal :is-open="isCreateFolderOpen" @close="isCreateFolderOpen = false" />
        <CreateDocumentModal
            v-model="isCreateDocOpen"
            :folder-uuid="drive.currentFolderUuid"
            :default-type="defaultDocType"
            @created="handleDocCreated"
        />
        <OfficeEditorModal
            v-model="isOfficeModalOpen"
            :file="activeOfficeFile"
            @saved="handleOfficeSaved"
        />
        <FilePreviewModal
            :file="activePreviewFile"
            @close="activePreviewFile = null"
            @download="handleDownload"
            @share="f => openShare(f, 'file')"
        />
        <ShareModal
            :item="activeShareItem"
            :item-type="activeShareType"
            @close="activeShareItem = null"
        />
        <RenameModal
            :item="activeRenameItem"
            @close="activeRenameItem = null"
            @save="handleRenameSave"
        />
        <MoveItemModal
            :item="activeMoveItem"
            :item-type="activeMoveType"
            @close="activeMoveItem = null"
            @moved="loadCurrentFolder"
        />
    </div>
</template>
