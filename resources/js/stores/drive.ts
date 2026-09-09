import { defineStore } from 'pinia';
import { ref } from 'vue';
import http from '@/utils/http';
import type { FileItem, Folder, FolderPathItem } from '@/types';
import { useUiStore } from './ui';

export const useDriveStore = defineStore('drive', () => {
    const currentFolderUuid = ref<string | null>(null);
    const currentFolder = ref<Folder | null>(null);
    const folders = ref<Folder[]>([]);
    const files = ref<FileItem[]>([]);
    const breadcrumbs = ref<FolderPathItem[]>([]);
    const viewMode = ref<'grid' | 'list'>('grid');
    const isLoading = ref(false);
    const currentCategory = ref<string | null>(null);

    const ui = useUiStore();

    async function fetchDrive(folderUuid: string | null = null, filter?: string) {
        isLoading.value = true;
        currentFolderUuid.value = folderUuid;

        try {
            if (folderUuid) {
                const folderRes = await http.get(`/folders/${folderUuid}`);
                currentFolder.value = folderRes.data.data;
                breadcrumbs.value = folderRes.data.data.path || [];
            } else {
                currentFolder.value = null;
                breadcrumbs.value = [];
            }

            // Fetch Subfolders
            if (!filter || filter === 'drive') {
                const foldersRes = await http.get('/folders', {
                    params: { parent_uuid: folderUuid },
                });
                folders.value = foldersRes.data.data;
            } else {
                folders.value = [];
            }

            // Fetch Files
            const filesRes = await http.get('/files', {
                params: {
                    folder_uuid: folderUuid,
                    filter: filter,
                    category: currentCategory.value,
                },
            });
            files.value = filesRes.data.data;
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal memuat isi drive.', 'error');
        } finally {
            isLoading.value = false;
        }
    }

    async function createFolder(name: string, color: string = '#3B82F6') {
        try {
            const res = await http.post('/folders', {
                name,
                color,
                parent_uuid: currentFolderUuid.value,
            });
            folders.value.unshift(res.data.data);
            ui.notify(`Folder "${name}" berhasil dibuat.`, 'success');
            return res.data.data;
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal membuat folder.', 'error');
            throw error;
        }
    }

    async function renameFolder(uuid: string, name: string) {
        try {
            const res = await http.patch(`/folders/${uuid}`, { name });
            const idx = folders.value.findIndex(f => f.uuid === uuid);
            if (idx !== -1) {
                folders.value[idx].name = name;
            }
            ui.notify('Nama folder berhasil diubah.', 'success');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal mengubah nama folder.', 'error');
        }
    }

    async function deleteFolder(uuid: string) {
        try {
            await http.delete(`/folders/${uuid}`);
            folders.value = folders.value.filter(f => f.uuid !== uuid);
            ui.notify('Folder dipindahkan ke sampah.', 'info');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal menghapus folder.', 'error');
        }
    }

    async function renameFile(uuid: string, name: string) {
        try {
            const res = await http.patch(`/files/${uuid}`, { name });
            const idx = files.value.findIndex(f => f.uuid === uuid);
            if (idx !== -1) {
                files.value[idx].original_name = name;
            }
            ui.notify('Nama berkas berhasil diubah.', 'success');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal mengubah nama berkas.', 'error');
        }
    }

    async function toggleStarFile(file: FileItem) {
        try {
            const updatedStar = !file.is_starred;
            await http.patch(`/files/${file.uuid}`, { is_starred: updatedStar });
            file.is_starred = updatedStar;
            ui.notify(updatedStar ? 'Ditambahkan ke Berbintang.' : 'Dihapus dari Berbintang.', 'info');
        } catch (error: any) {
            ui.notify('Gagal memperbarui status bintang.', 'error');
        }
    }

    async function trashFile(uuid: string) {
        try {
            await http.delete(`/files/${uuid}`);
            files.value = files.value.filter(f => f.uuid !== uuid);
            ui.notify('Berkas dipindahkan ke sampah.', 'info');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal memindahkan berkas ke sampah.', 'error');
        }
    }

    async function restoreFile(id: number) {
        try {
            await http.post(`/files/${id}/restore`);
            files.value = files.value.filter(f => f.id !== id);
            ui.notify('Berkas berhasil dipulihkan.', 'success');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal memulihkan berkas.', 'error');
        }
    }

    async function permanentDeleteFile(id: number) {
        try {
            await http.delete(`/files/${id}/permanent`);
            files.value = files.value.filter(f => f.id !== id);
            ui.notify('Berkas dihapus secara permanen.', 'success');
        } catch (error: any) {
            ui.notify(error.response?.data?.message || 'Gagal menghapus berkas.', 'error');
        }
    }

    return {
        currentFolderUuid,
        currentFolder,
        folders,
        files,
        breadcrumbs,
        viewMode,
        isLoading,
        currentCategory,
        fetchDrive,
        createFolder,
        renameFolder,
        deleteFolder,
        renameFile,
        toggleStarFile,
        trashFile,
        restoreFile,
        permanentDeleteFile,
    };
});
