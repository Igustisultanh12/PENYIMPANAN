import http from './http';
import type { FileItem } from '../types';

export async function downloadFile(file: FileItem) {
    if (!file || !file.uuid) return;

    try {
        // 1. Request signed temporary download URL from backend
        const res = await http.get(`/files/${file.uuid}/download`);
        if (res.data?.download_url) {
            const a = document.createElement('a');
            a.href = res.data.download_url;
            a.download = file.original_name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            return;
        }
    } catch (e) {
        console.warn('Signed download URL request failed, attempting direct blob download fallback:', e);
    }

    // 2. Fallback: Direct authenticated blob download
    try {
        const response = await http.get(`/files/${file.uuid}/preview`, {
            responseType: 'blob',
        });
        const blob = new Blob([response.data], {
            type: file.mime_type || 'application/octet-stream',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.original_name;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (err: any) {
        console.error('File download failed:', err);
        alert('Gagal mengunduh berkas. Pastikan Anda memiliki izin akses.');
    }
}
