import { defineStore } from 'pinia';
import { ref } from 'vue';
import http from '@/utils/http';
import type { UploadItem } from '@/types';
import { useDriveStore } from './drive';
import { useUiStore } from './ui';

export const useUploadStore = defineStore('upload', () => {
    const queue = ref<UploadItem[]>([]);
    const isTrayOpen = ref(false);
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB chunks

    const driveStore = useDriveStore();
    const ui = useUiStore();

    function addFilesToQueue(files: FileList | File[], folderUuid: string | null = null) {
        const fileArray = Array.from(files);

        for (const file of fileArray) {
            const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
            const item: UploadItem = {
                id: Math.random().toString(36).substring(2, 9),
                file,
                name: file.name,
                size: file.size,
                uploadedBytes: 0,
                progress: 0,
                status: 'pending',
                speed: '0 KB/s',
                eta: '--',
                currentChunk: 0,
                totalChunks,
                folderUuid,
            };

            queue.value.unshift(item);
            isTrayOpen.value = true;
            processUpload(item);
        }
    }

    async function processUpload(item: UploadItem) {
        if (item.status === 'paused' || item.status === 'completed') return;

        item.status = 'uploading';
        const file = item.file;
        const totalSize = file.size;

        try {
            // Step 1: Initiate upload session if not already initiated
            if (!item.uploadId) {
                const initRes = await http.post('/uploads/initiate', {
                    filename: file.name,
                    total_size: totalSize,
                    chunk_size: CHUNK_SIZE,
                    total_chunks: item.totalChunks,
                    folder_uuid: item.folderUuid,
                    mime_type: file.type,
                });

                item.uploadId = initRes.data.data.upload_id;
            }

            // Step 2: Query uploaded chunks for resumability
            const statusRes = await http.get(`/uploads/${item.uploadId}/status`);
            const completedIndices = new Set<number>(statusRes.data.uploaded_chunks || []);

            let lastTime = Date.now();
            let lastBytes = item.uploadedBytes;

            // Step 3: Stream chunks sequentially
            for (let i = 0; i < item.totalChunks; i++) {
                if ((item.status as string) === 'paused') return;

                item.currentChunk = i + 1;

                if (completedIndices.has(i)) {
                    // Chunk already on server
                    const chunkBytes = Math.min(CHUNK_SIZE, totalSize - (i * CHUNK_SIZE));
                    item.uploadedBytes = Math.min(totalSize, (i + 1) * CHUNK_SIZE);
                    item.progress = Math.round((item.uploadedBytes / totalSize) * 100);
                    continue;
                }

                const start = i * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, totalSize);
                const chunkBlob = file.slice(start, end);

                const formData = new FormData();
                formData.append('chunk_index', i.toString());
                formData.append('total_chunks', item.totalChunks.toString());
                formData.append('total_size', totalSize.toString());
                formData.append('original_name', file.name);
                formData.append('chunk', chunkBlob, file.name);

                // Retry logic (up to 3 times)
                let attempts = 0;
                let success = false;

                while (attempts < 3 && !success) {
                    try {
                        await http.post(`/uploads/${item.uploadId}/chunk`, formData, {
                            headers: { 'Content-Type': 'multipart/form-data' },
                        });
                        success = true;
                    } catch (err) {
                        attempts++;
                        if (attempts >= 3) throw err;
                        await new Promise(r => setTimeout(r, 1000));
                    }
                }

                // Update progress, speed, and ETA
                item.uploadedBytes = end;
                item.progress = Math.round((end / totalSize) * 100);

                const now = Date.now();
                const timeDiff = (now - lastTime) / 1000;
                if (timeDiff >= 0.5) {
                    const bytesDiff = item.uploadedBytes - lastBytes;
                    const bytesPerSec = bytesDiff / timeDiff;

                    // Format speed
                    if (bytesPerSec > 1024 * 1024) {
                        item.speed = (bytesPerSec / (1024 * 1024)).toFixed(1) + ' MB/s';
                    } else {
                        item.speed = (bytesPerSec / 1024).toFixed(0) + ' KB/s';
                    }

                    // Format ETA
                    const remainingBytes = totalSize - item.uploadedBytes;
                    const etaSeconds = bytesPerSec > 0 ? Math.round(remainingBytes / bytesPerSec) : 0;
                    item.eta = etaSeconds < 60 ? `${etaSeconds}s` : `${Math.round(etaSeconds / 60)}m`;

                    lastTime = now;
                    lastBytes = item.uploadedBytes;
                }
            }

            // Step 4: Finalize assembly
            const finalizeRes = await http.post(`/uploads/${item.uploadId}/finalize`);

            item.status = 'completed';
            item.progress = 100;
            item.speed = 'Selesai';
            item.eta = '0s';

            ui.notify(`Berkas "${item.name}" berhasil diunggah.`, 'success');

            // Refresh current folder view if matched
            if (item.folderUuid === driveStore.currentFolderUuid) {
                driveStore.fetchDrive(driveStore.currentFolderUuid);
            }
        } catch (error: any) {
            item.status = 'error';
            item.error = error.response?.data?.message || 'Gagal mengunggah berkas.';
            ui.notify(`Gagal mengunggah "${item.name}": ${item.error}`, 'error');
        }
    }

    function pauseUpload(item: UploadItem) {
        if (item.status === 'uploading') {
            item.status = 'paused';
            item.speed = 'Dijeda';
        }
    }

    function resumeUpload(item: UploadItem) {
        if (item.status === 'paused' || item.status === 'error') {
            processUpload(item);
        }
    }

    async function cancelUpload(item: UploadItem) {
        item.status = 'paused';
        if (item.uploadId) {
            try {
                await http.delete(`/uploads/${item.uploadId}`);
            } catch {
                // Ignore cancel error
            }
        }
        queue.value = queue.value.filter(i => i.id !== item.id);
    }

    function clearCompleted() {
        queue.value = queue.value.filter(i => i.status !== 'completed');
    }

    return {
        queue,
        isTrayOpen,
        addFilesToQueue,
        pauseUpload,
        resumeUpload,
        cancelUpload,
        clearCompleted,
    };
});
