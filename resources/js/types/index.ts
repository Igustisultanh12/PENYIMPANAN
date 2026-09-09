export interface User {
    uuid: string;
    name: string;
    email: string;
    whatsapp?: string | null;
    role: 'user' | 'admin' | 'super_admin';
    status: 'pending_verification' | 'active' | 'suspended';
    email_verified: boolean;
    avatar_url?: string | null;
    two_factor_enabled?: boolean;
    storage?: {
        used_bytes: number;
        quota_bytes: number;
        usage_percentage: number;
    };
    profile?: {
        bio?: string;
        timezone?: string;
        locale?: string;
    };
}

export interface StorageCategoryStat {
    bytes: number;
    formatted: string;
}

export interface StorageStats {
    quota_bytes: number;
    used_bytes: number;
    free_bytes: number;
    percentage: number;
    status: 'normal' | 'warning' | 'critical' | 'blocked';
    categories: {
        images: StorageCategoryStat;
        videos: StorageCategoryStat;
        documents: StorageCategoryStat;
        audio: StorageCategoryStat;
        archives: StorageCategoryStat;
        other: StorageCategoryStat;
    };
    human_readable: {
        quota: string;
        used: string;
        free: string;
    };
}

export interface FileMetadata {
    width?: number | null;
    height?: number | null;
    duration?: number | null;
    thumbnail_path?: string | null;
    extra_attributes?: Record<string, any> | null;
}

export interface FileItem {
    id: number;
    uuid: string;
    original_name: string;
    storage_name: string;
    mime_type: string;
    extension: string;
    size: number;
    checksum: string;
    disk: string;
    visibility: 'private' | 'shared' | 'public';
    status: 'uploading' | 'ready' | 'quarantined' | 'error';
    version: number;
    is_starred: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    category: 'image' | 'video' | 'audio' | 'document' | 'archive' | 'other';
    human_size: string;
    metadata?: FileMetadata | null;
    folder?: Folder | null;
}

export interface FolderPathItem {
    uuid: string;
    name: string;
}

export interface Folder {
    id: number;
    uuid: string;
    name: string;
    color: string;
    is_starred: boolean;
    parent_id?: number | null;
    path?: FolderPathItem[];
    files_count?: number;
    children_count?: number;
    created_at: string;
    updated_at: string;
}

export interface SharePermissions {
    can_view: boolean;
    can_download: boolean;
    can_edit: boolean;
    can_manage: boolean;
}

export interface Share {
    id: number;
    uuid: string;
    token: string;
    share_url?: string;
    shareable_type?: string;
    permission: 'viewer' | 'commenter' | 'editor';
    access_type: 'public_link' | 'specific_user' | 'specific_whatsapp';
    target_contact?: string | null;
    allow_download: boolean;
    expires_at?: string | null;
    owner?: {
        name: string;
        email: string;
        avatar_url?: string;
    };
    item_type?: string;
    item?: FileItem | Folder;
    permissions?: SharePermissions;
}

export interface UploadItem {
    id: string;
    file: File;
    name: string;
    size: number;
    uploadedBytes: number;
    progress: number;
    status: 'pending' | 'uploading' | 'completed' | 'paused' | 'error';
    speed: string; // e.g. "2.4 MB/s"
    eta: string; // e.g. "15s"
    currentChunk: number;
    totalChunks: number;
    uploadId?: string;
    folderUuid?: string | null;
    error?: string;
}

export interface ToastMessage {
    id: string;
    type: 'success' | 'error' | 'info' | 'warning';
    title?: string;
    message: string;
}
