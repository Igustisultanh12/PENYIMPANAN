<?php

namespace App\Services;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Enums\FileStatus;
use App\Enums\FileVisibility;
use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\FileVersion;
use App\Models\OfficeSession;
use App\Models\User;
use App\Services\Storage\StorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class OfficeEditorService
{
    public function __construct(
        protected StorageService $storageService,
        protected DocumentLockService $lockService,
        protected SyncChangeLogger $syncLogger,
        protected CalculateStorageUsageAction $storageUsageAction
    ) {}

    /**
     * Create a new document, spreadsheet, or presentation file.
     */
    public function createDocument(
        User $user,
        string $name,
        string $type,
        ?int $folderId = null,
        ?string $initialContent = null
    ): FileItem {
        $meta = $this->getTypeMetadata($type);
        $extension = $meta['extension'];
        $mimeType = $meta['mime_type'];

        // Normalize filename with proper extension
        if (!str_ends_with(strtolower($name), '.' . $extension)) {
            $fileName = $name . '.' . $extension;
        } else {
            $fileName = $name;
        }

        $fileUuid = (string) Str::uuid();
        $disk = config('filesystems.default', 'local');
        $storagePath = $this->storageService->generateStoragePath($user, $fileUuid, $extension);

        // Generate valid OpenXML binary bytes or use provided initial content
        $binaryBytes = $initialContent ?: $this->generateInitialBinary($type, $name);
        $size = strlen($binaryBytes);
        $checksum = hash('sha256', $binaryBytes);

        // Save physical file
        $this->storageService->disk($disk)->put($storagePath, $binaryBytes);

        // Persist FileItem
        $file = FileItem::create([
            'uuid' => $fileUuid,
            'owner_id' => $user->id,
            'folder_id' => $folderId,
            'original_name' => $fileName,
            'storage_name' => basename($storagePath),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $size,
            'checksum' => $checksum,
            'disk' => $disk,
            'storage_path' => $storagePath,
            'visibility' => FileVisibility::PRIVATE,
            'status' => FileStatus::READY,
            'version' => 1,
        ]);

        // Create Version 1
        FileVersion::create([
            'file_id' => $file->id,
            'version_number' => 1,
            'storage_name' => $file->storage_name,
            'storage_path' => $file->storage_path,
            'size' => $size,
            'checksum' => $checksum,
            'disk' => $disk,
        ]);

        // Increment storage usage
        $this->storageUsageAction->increment($user, $file);

        // Record Change Feed
        $this->syncLogger->logFileChange($user, $file, 'created');

        // Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'office.document_created',
            'target_type' => 'File',
            'target_id' => $file->id,
            'metadata' => [
                'name' => $fileName,
                'type' => $type,
                'size' => $size,
            ],
        ]);

        return $file;
    }

    /**
     * Start or resume an office editing session.
     */
    public function createSession(FileItem $file, User $user, string $mode = 'edit', ?string $deviceId = null): array
    {
        $docType = $this->detectDocumentType($file->extension);

        // Check/Acquire document lock if editing
        $lockResult = null;
        if ($mode === 'edit') {
            $lockResult = $this->lockService->acquireLock($file, $user, $deviceId, 300);
            if (!$lockResult['locked']) {
                // If already locked by another user, fallback to read-only view
                $mode = 'view';
            }
        }

        // Read physical file bytes if exists
        $disk = $this->storageService->disk($file->disk);
        $binaryBytes = null;
        $binaryBase64 = null;
        if ($disk->exists($file->storage_path)) {
            $binaryBytes = $disk->get($file->storage_path);
            if (!empty($binaryBytes)) {
                $binaryBase64 = base64_encode($binaryBytes);
            }
        }

        // Extract real content from physical file
        $realDraft = $this->extractDraftFromPhysicalFile($file, $docType, $binaryBytes);

        // Check if there is an active session with draft content
        $existingSession = OfficeSession::where('file_id', $file->id)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        $needsRefreshDraft = false;
        if ($existingSession) {
            $existingDraftStr = (string)$existingSession->draft_content;
            if (
                str_contains($existingDraftStr, 'Sample Data 1') ||
                str_contains($existingDraftStr, 'This is a sample document') ||
                str_contains($existingDraftStr, 'Created in MyStorage Online Suite') ||
                str_contains($existingDraftStr, '15000000') ||
                empty($existingDraftStr)
            ) {
                $needsRefreshDraft = true;
            }
        }

        if ($existingSession && !$needsRefreshDraft) {
            $session = $existingSession;
            $session->update([
                'mode' => $mode,
                'expires_at' => now()->addHours(4),
            ]);
        } else {
            $draftContent = $realDraft ?: $this->getDefaultDraftStructure($docType, $file->original_name);

            if ($existingSession) {
                $session = $existingSession;
                $session->update([
                    'mode' => $mode,
                    'draft_content' => json_encode($draftContent),
                    'expires_at' => now()->addHours(4),
                ]);
            } else {
                $session = OfficeSession::create([
                    'file_id' => $file->id,
                    'user_id' => $user->id,
                    'session_token' => Str::random(64),
                    'document_type' => $docType,
                    'mode' => $mode,
                    'draft_content' => json_encode($draftContent),
                    'expires_at' => now()->addHours(4),
                ]);
            }
        }

        $draftDecoded = json_decode($session->draft_content, true) ?: $session->draft_content;
        $htmlContent = is_array($draftDecoded) ? ($draftDecoded['html'] ?? null) : (is_string($draftDecoded) ? $draftDecoded : null);

        return [
            'session_token' => $session->session_token,
            'mode' => $session->mode,
            'document_type' => $session->document_type,
            'file' => [
                'uuid' => $file->uuid,
                'name' => $file->original_name,
                'size' => $file->size,
                'version' => $file->version,
                'extension' => $file->extension,
                'updated_at' => $file->updated_at,
            ],
            'draft' => $draftDecoded,
            'binary_base64' => $binaryBase64,
            'html_content' => $htmlContent,
            'last_autosave_at' => $session->last_autosave_at?->toIso8601String(),
            'lock' => $lockResult,
        ];
    }

    /**
     * Autosave draft content during active editing.
     */
    public function saveDraft(OfficeSession $session, mixed $content): void
    {
        $json = is_array($content) ? json_encode($content) : (string) $content;

        $session->update([
            'draft_content' => $json,
            'last_autosave_at' => now(),
            'expires_at' => now()->addHours(4),
        ]);

        // Renew file lock if applicable
        $activeLock = $this->lockService->checkLock($session->file);
        if ($activeLock && $activeLock->user_id === $session->user_id) {
            $this->lockService->renewLock($session->file, $activeLock->lock_token, 300);
        }
    }

    /**
     * Commit changes to cloud storage and create a new version.
     */
    public function commitSession(
        OfficeSession $session,
        ?string $binaryContent = null,
        mixed $draftContent = null,
        ?string $deviceId = null
    ): FileItem {
        $file = $session->file;
        $user = $session->user;

        if ($draftContent !== null) {
            $this->saveDraft($session, $draftContent);
        }

        // Generate updated binary payload
        $contentToStore = $binaryContent ?: $this->generateBinaryFromDraft(
            $session->document_type,
            $file->original_name,
            $draftContent ?: json_decode($session->draft_content, true),
            $file->extension
        );

        $newSize = strlen($contentToStore);
        $newChecksum = hash('sha256', $contentToStore);
        $disk = $file->disk ?: config('filesystems.default', 'local');

        // New version storage path
        $newVersionNumber = $file->version + 1;
        $year = date('Y');
        $month = date('m');
        $versionStoragePath = "tenants/{$user->uuid}/{$year}/{$month}/{$file->uuid}_v{$newVersionNumber}.{$file->extension}";

        $this->storageService->disk($disk)->put($versionStoragePath, $contentToStore);

        // Create version record
        FileVersion::create([
            'file_id' => $file->id,
            'version_number' => $newVersionNumber,
            'storage_name' => basename($versionStoragePath),
            'storage_path' => $versionStoragePath,
            'size' => $newSize,
            'checksum' => $newChecksum,
            'disk' => $disk,
        ]);

        // Update FileItem
        $file->update([
            'version' => $newVersionNumber,
            'size' => $newSize,
            'checksum' => $newChecksum,
            'storage_path' => $versionStoragePath,
            'storage_name' => basename($versionStoragePath),
        ]);

        // Storage usage difference
        $this->storageUsageAction->recalculate($user);

        // Record Change Feed for Desktop Sync
        $this->syncLogger->logFileChange($user, $file, 'updated', $deviceId);

        // Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'office.document_saved',
            'target_type' => 'File',
            'target_id' => $file->id,
            'metadata' => [
                'name' => $file->original_name,
                'version' => $newVersionNumber,
                'size' => $newSize,
            ],
        ]);

        return $file;
    }

    /**
     * Map document type to extension and mime.
     */
    public function getTypeMetadata(string $type): array
    {
        return match ($type) {
            'spreadsheet' => [
                'extension' => 'xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'presentation' => [
                'extension' => 'pptx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            default => [
                'extension' => 'docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        };
    }

    /**
     * Detect document type from extension.
     */
    public function detectDocumentType(string $extension): string
    {
        $ext = strtolower($extension);
        if (in_array($ext, ['xlsx', 'xls', 'csv', 'ods', 'tsv'])) {
            return 'spreadsheet';
        }
        if (in_array($ext, ['pptx', 'ppt', 'odp'])) {
            return 'presentation';
        }
        return 'document';
    }

    /**
     * Generate standard OpenXML zip file bytes for new documents.
     */
    public function generateInitialBinary(string $type, string $title): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'mystorage_doc_');
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create temporary OpenXML file archive.");
        }

        if ($type === 'spreadsheet') {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>' . htmlspecialchars($title) . '</t></is></c></row></sheetData></worksheet>');
        } elseif ($type === 'presentation') {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
            $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>');
            $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst></p:presentation>');
            $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/><p:sp><p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="ctrTitle"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' . htmlspecialchars($title) . '</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>');
        } else {
            // Default: Word Document
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
            $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>' . htmlspecialchars($title) . '</w:t></w:r></w:p></w:body></w:document>');
        }

        $zip->close();
        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Generate binary OpenXML bytes or raw text from JSON/HTML draft editor state.
     */
    public function generateBinaryFromDraft(string $type, string $title, mixed $draft, ?string $extension = null): string
    {
        $ext = strtolower($extension ?? '');

        // 1. Plain text / Markdown / Log / CSV raw formats
        if (in_array($ext, ['txt', 'md', 'log', 'json'])) {
            $text = is_string($draft) ? $draft : ($draft['html'] ?? '');
            $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
            $text = preg_replace('/<\/p>/i', "\n", $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $text;
        }

        // 2. Multi-slide PowerPoint Presentation (.pptx)
        if ($type === 'presentation' || $ext === 'pptx') {
            $slides = is_array($draft) ? ($draft['slides'] ?? []) : [];
            return $this->generatePptxFromSlides($title, $slides);
        }

        // 3. Word Document (.docx)
        if ($type === 'document' || $ext === 'docx') {
            $html = is_string($draft) ? $draft : ($draft['html'] ?? '');
            if (empty($html) && is_array($draft) && !empty($draft['paragraphs'])) {
                $html = '';
                foreach ($draft['paragraphs'] as $p) {
                    $t = is_array($p) ? ($p['text'] ?? '') : (string)$p;
                    $html .= "<p>{$t}</p>";
                }
            }
            return $this->generateDocxFromHtml($title, $html ?: "<p>{$title}</p>");
        }

        // 4. Spreadsheet fallback (.xlsx)
        if (empty($draft) || !is_array($draft)) {
            return $this->generateInitialBinary($type, $title);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'mystorage_save_');
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->generateInitialBinary($type, $title);
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $rows = $draft['rows'] ?? [];
        $rIndex = 1;
        foreach ($rows as $row) {
            $sheetXml .= "<row r=\"{$rIndex}\">";
            foreach ($row as $colLetter => $cellVal) {
                $cellEscaped = htmlspecialchars((string) ($cellVal ?? ''));
                $col = is_string($colLetter) && ctype_alpha($colLetter) ? strtoupper($colLetter) : $this->indexToColumnLetter((int)$colLetter);
                $sheetXml .= "<c r=\"{$col}{$rIndex}\" t=\"inlineStr\"><is><t>{$cellEscaped}</t></is></c>";
            }
            $sheetXml .= "</row>";
            $rIndex++;
        }
        $sheetXml .= '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Build standard .docx binary from HTML content.
     */
    protected function generateDocxFromHtml(string $title, string $html): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'docx_save_');
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->generateInitialBinary('document', $title);
        }

        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $docXml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';

        $cleanHtml = str_replace(["\r\n", "\r"], "\n", $html);
        $parts = preg_split('/(<\/(?:p|h1|h2|h3|li|div)>|<br\s*\/?>)/i', $cleanHtml);

        $hasContent = false;
        foreach ($parts as $part) {
            $trimmed = trim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($trimmed === '' && $hasContent) {
                continue;
            }

            $escaped = htmlspecialchars($trimmed, ENT_XML1, 'UTF-8');
            $docXml .= "<w:p><w:r><w:t>{$escaped}</w:t></w:r></w:p>";
            $hasContent = true;
        }

        if (!$hasContent) {
            $docXml .= '<w:p><w:r><w:t></w:t></w:r></w:p>';
        }

        $docXml .= '</w:body></w:document>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', $docXml);

        $zip->close();
        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Build multi-slide .pptx binary from slide array.
     */
    protected function generatePptxFromSlides(string $title, array $slides): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'pptx_save_');
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->generateInitialBinary('presentation', $title);
        }

        if (empty($slides)) {
            $slides = [['title' => $title, 'subtitle' => '']];
        }

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>';

        $presRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $sldIdLst = '';

        foreach ($slides as $index => $slide) {
            $slideNum = $index + 1;
            $relId = "rId" . ($slideNum + 1);
            $contentTypesXml .= "<Override PartName=\"/ppt/slides/slide{$slideNum}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.presentationml.slide+xml\"/>";
            $presRelsXml .= "<Relationship Id=\"{$relId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide\" Target=\"slides/slide{$slideNum}.xml\"/>";
            $sldIdLst .= "<p:sldId id=\"" . (255 + $slideNum) . "\" r:id=\"{$relId}\"/>";

            $st = htmlspecialchars($slide['title'] ?? '', ENT_XML1, 'UTF-8');
            $sb = htmlspecialchars($slide['subtitle'] ?? ($slide['body'] ?? ''), ENT_XML1, 'UTF-8');

            $slideXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><p:sld xmlns:p=\"http://schemas.openxmlformats.org/presentationml/2006/main\" xmlns:a=\"http://schemas.openxmlformats.org/drawingml/2006/main\"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id=\"1\" name=\"\"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/><p:sp><p:nvSpPr><p:cNvPr id=\"2\" name=\"Title\"/><p:cNvSpPr><a:spLocks noGrp=\"1\"/></p:cNvSpPr><p:nvPr><p:ph type=\"ctrTitle\"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$st}</a:t></a:r></a:p></p:txBody></p:sp><p:sp><p:nvSpPr><p:cNvPr id=\"3\" name=\"Subtitle\"/><p:cNvSpPr><a:spLocks noGrp=\"1\"/></p:cNvSpPr><p:nvPr><p:ph type=\"subTitle\" idx=\"1\"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$sb}</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>";
            $zip->addFromString("ppt/slides/slide{$slideNum}.xml", $slideXml);
        }

        $contentTypesXml .= '</Types>';
        $presRelsXml .= '</Relationships>';

        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $presRelsXml);
        $zip->addFromString('ppt/presentation.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><p:presentation xmlns:p=\"http://schemas.openxmlformats.org/presentationml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><p:sldIdLst>{$sldIdLst}</p:sldIdLst></p:presentation>");

        $zip->close();
        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Extract real draft structure and content from the physical file.
     */
    public function extractDraftFromPhysicalFile(FileItem $file, string $docType, ?string $binaryBytes): ?array
    {
        if (empty($binaryBytes)) {
            return null;
        }

        $extension = strtolower($file->extension ?? pathinfo($file->original_name, PATHINFO_EXTENSION));

        // 1. Plain text / Markdown / CSV / TSV / Log / JSON
        if (in_array($extension, ['txt', 'csv', 'tsv', 'log', 'md', 'json'])) {
            if ($extension === 'csv' || $extension === 'tsv') {
                $delimiter = $extension === 'tsv' ? "\t" : ",";
                $lines = explode("\n", str_replace("\r", "", $binaryBytes));
                $rows = [];
                foreach ($lines as $line) {
                    if (trim($line) === '') continue;
                    $cols = str_getcsv($line, $delimiter);
                    $rowMap = [];
                    foreach ($cols as $cIdx => $val) {
                        $colLetter = $this->indexToColumnLetter($cIdx);
                        $rowMap[$colLetter] = $val;
                    }
                    $rows[] = $rowMap;
                }
                return [
                    'sheets' => ['Sheet1'],
                    'activeSheet' => 'Sheet1',
                    'rows' => $rows,
                ];
            } else {
                $html = nl2br(htmlspecialchars($binaryBytes));
                return [
                    'title' => $file->original_name,
                    'html' => "<div style=\"font-family: monospace; white-space: pre-wrap;\">{$html}</div>",
                    'paragraphs' => [],
                ];
            }
        }

        // 2. OpenXML Word (.docx)
        if ($extension === 'docx') {
            $html = $this->parseDocxToHtml($binaryBytes);
            if (!empty($html)) {
                return [
                    'title' => $file->original_name,
                    'html' => $html,
                    'paragraphs' => [],
                ];
            }
        }

        // 3. OpenXML Presentation (.pptx)
        if ($extension === 'pptx') {
            $slides = $this->parsePptxToSlides($binaryBytes, $file->original_name);
            if (!empty($slides)) {
                return ['slides' => $slides];
            }
        }

        // 4. Spreadsheets (.xlsx, .xls)
        if (in_array($extension, ['xlsx', 'xls', 'ods'])) {
            return [
                'sheets' => ['Sheet1'],
                'activeSheet' => 'Sheet1',
                'rows' => [],
            ];
        }

        return null;
    }

    /**
     * Convert 0-based column index to Excel column letters (0->A, 25->Z, 26->AA).
     */
    protected function indexToColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }

    /**
     * Parse OpenXML .docx binary to formatted HTML.
     */
    public function parseDocxToHtml(string $binaryBytes): string
    {
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'docx_');
            file_put_contents($tempPath, $binaryBytes);
            $zip = new ZipArchive();
            if ($zip->open($tempPath) !== true) {
                @unlink($tempPath);
                return '';
            }
            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();
            @unlink($tempPath);

            if (!$xmlContent) return '';

            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$xml) return '';

            $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = $xml->xpath('//w:p');
            if (empty($paragraphs)) return '';

            $html = '';
            foreach ($paragraphs as $p) {
                $pText = '';
                $style = '';
                $pStyleNodes = $p->xpath('.//w:pStyle/@w:val');
                if (!empty($pStyleNodes)) {
                    $styleName = (string)$pStyleNodes[0];
                    if (stripos($styleName, 'Heading1') !== false || $styleName === '1') $style = 'h1';
                    elseif (stripos($styleName, 'Heading2') !== false || $styleName === '2') $style = 'h2';
                    elseif (stripos($styleName, 'Heading3') !== false || $styleName === '3') $style = 'h3';
                }

                $runs = $p->xpath('.//w:r');
                foreach ($runs as $r) {
                    $isBold = !empty($r->xpath('.//w:b'));
                    $isItalic = !empty($r->xpath('.//w:i'));
                    $isUnderline = !empty($r->xpath('.//w:u'));
                    $texts = $r->xpath('.//w:t');
                    $runText = '';
                    foreach ($texts as $t) {
                        $runText .= (string)$t;
                    }
                    if ($runText === '') continue;

                    $runHtml = htmlspecialchars($runText);
                    if ($isBold) $runHtml = "<b>{$runHtml}</b>";
                    if ($isItalic) $runHtml = "<i>{$runHtml}</i>";
                    if ($isUnderline) $runHtml = "<u>{$runHtml}</u>";
                    $pText .= $runHtml;
                }

                if ($pText === '') {
                    $html .= '<p><br/></p>';
                } elseif ($style === 'h1') {
                    $html .= "<h1 style=\"font-size: 1.875rem; font-weight: bold; margin-bottom: 0.75rem;\">{$pText}</h1>";
                } elseif ($style === 'h2') {
                    $html .= "<h2 style=\"font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;\">{$pText}</h2>";
                } elseif ($style === 'h3') {
                    $html .= "<h3 style=\"font-size: 1.25rem; font-weight: bold; margin-bottom: 0.5rem;\">{$pText}</h3>";
                } else {
                    $html .= "<p style=\"margin-bottom: 0.5rem;\">{$pText}</p>";
                }
            }

            return $html;
        } catch (\Throwable $e) {
            Log::warning("Gagal parse docx ke HTML: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Parse OpenXML .pptx binary to slide objects.
     */
    public function parsePptxToSlides(string $binaryBytes, string $fileName): array
    {
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'pptx_');
            file_put_contents($tempPath, $binaryBytes);
            $zip = new ZipArchive();
            if ($zip->open($tempPath) !== true) {
                @unlink($tempPath);
                return [];
            }

            $slides = [];
            for ($i = 1; $i <= 100; $i++) {
                $slideXml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                if (!$slideXml) break;

                $xml = simplexml_load_string($slideXml, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_NOERROR | LIBXML_NOWARNING);
                if (!$xml) continue;

                $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $textNodes = $xml->xpath('//a:t');
                $texts = [];
                foreach ($textNodes as $tn) {
                    $str = trim((string)$tn);
                    if ($str !== '') $texts[] = $str;
                }

                $title = !empty($texts) ? array_shift($texts) : "Slide {$i}";
                $body = implode("\n\n", $texts);

                $slides[] = [
                    'id' => $i,
                    'title' => $title,
                    'subtitle' => $body,
                    'notes' => '',
                    'background' => $i === 1 ? '#1e293b' : '#0f172a',
                ];
            }

            $zip->close();
            @unlink($tempPath);

            return $slides;
        } catch (\Throwable $e) {
            Log::warning("Gagal parse pptx ke slides: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Provide default clean JSON schema for brand new documents (no dummy sales data).
     */
    public function getDefaultDraftStructure(string $type, string $title): array
    {
        return match ($type) {
            'spreadsheet' => [
                'sheets' => ['Sheet1'],
                'activeSheet' => 'Sheet1',
                'rows' => [],
            ],
            'presentation' => [
                'slides' => [
                    [
                        'id' => 1,
                        'title' => pathinfo($title, PATHINFO_FILENAME),
                        'subtitle' => '',
                        'notes' => '',
                        'background' => '#1e293b',
                    ],
                ],
            ],
            default => [
                'title' => $title,
                'html' => '<p><br/></p>',
                'paragraphs' => [],
            ],
        };
    }
}
