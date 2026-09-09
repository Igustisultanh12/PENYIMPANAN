import { ref } from 'vue';

type Locale = 'id' | 'en';

const currentLocale = ref<Locale>((localStorage.getItem('mystorage_locale') as Locale) || 'id');

const dictionary: Record<Locale, Record<string, string>> = {
    id: {
        'nav.drive': 'Drive Saya',
        'nav.recent': 'Terbaru',
        'nav.starred': 'Berbintang',
        'nav.shared': 'Dibagikan',
        'nav.trash': 'Sampah',
        'nav.storage': 'Penyimpanan',
        'nav.security': 'Keamanan Akun',
        'nav.settings': 'Pengaturan',
        'storage.used': 'terpakai dari',
        'action.upload': 'Unggah Berkas',
        'action.new_folder': 'Folder Baru',
        'action.download': 'Unduh',
        'action.share': 'Bagikan',
        'action.rename': 'Ganti Nama',
        'action.move': 'Pindahkan',
        'action.delete': 'Hapus',
        'action.restore': 'Pulihkan',
        'action.permanent_delete': 'Hapus Permanen',
        'action.preview': 'Pratinjau',
        'search.placeholder': 'Cari berkas, folder, atau ekstensi...',
        'empty.no_files': 'Belum ada berkas di folder ini',
        'empty.upload_hint': 'Tarik dan lepas berkas ke sini atau gunakan tombol Unggah untuk memulai.',
        'filter.all': 'Semua',
        'filter.images': 'Gambar',
        'filter.documents': 'Dokumen',
        'filter.videos': 'Video',
        'filter.audio': 'Audio',
        'filter.archives': 'Arsip',
    },
    en: {
        'nav.drive': 'My Drive',
        'nav.recent': 'Recent',
        'nav.starred': 'Starred',
        'nav.shared': 'Shared with me',
        'nav.trash': 'Trash',
        'nav.storage': 'Storage',
        'nav.security': 'Security Center',
        'nav.settings': 'Settings',
        'storage.used': 'used of',
        'action.upload': 'Upload File',
        'action.new_folder': 'New Folder',
        'action.download': 'Download',
        'action.share': 'Share',
        'action.rename': 'Rename',
        'action.move': 'Move',
        'action.delete': 'Delete',
        'action.restore': 'Restore',
        'action.permanent_delete': 'Delete Permanently',
        'action.preview': 'Preview',
        'search.placeholder': 'Search files, folders, or extensions...',
        'empty.no_files': 'No files in this folder yet',
        'empty.upload_hint': 'Drag & drop files here or click Upload to get started.',
        'filter.all': 'All',
        'filter.images': 'Images',
        'filter.documents': 'Documents',
        'filter.videos': 'Videos',
        'filter.audio': 'Audio',
        'filter.archives': 'Archives',
    },
};

export function useI18n() {
    function t(key: string): string {
        return dictionary[currentLocale.value][key] || key;
    }

    function setLocale(locale: Locale) {
        currentLocale.value = locale;
        localStorage.setItem('mystorage_locale', locale);
    }

    return {
        locale: currentLocale,
        t,
        setLocale,
    };
}
