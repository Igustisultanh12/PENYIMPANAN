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

        // Check if there is an active session with draft content
        $existingSession = OfficeSession::where('file_id', $file->id)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingSession) {
            $session = $existingSession;
            $session->update([
                'mode' => $mode,
                'expires_at' => now()->addHours(4),
            ]);
        } else {
            // Create default draft structure if none exists
            $draftContent = $this->getDefaultDraftStructure($docType, $file->original_name);

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
            'draft' => json_decode($session->draft_content, true) ?: $session->draft_content,
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
            $draftContent ?: json_decode($session->draft_content, true)
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
        if (in_array($ext, ['xlsx', 'xls', 'csv', 'ods'])) {
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
     * Generate binary OpenXML bytes from JSON draft editor state.
     */
    public function generateBinaryFromDraft(string $type, string $title, ?array $draft): string
    {
        if (empty($draft)) {
            return $this->generateInitialBinary($type, $title);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'mystorage_save_');
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->generateInitialBinary($type, $title);
        }

        if ($type === 'spreadsheet') {
            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            $rows = $draft['rows'] ?? [];
            $rIndex = 1;
            foreach ($rows as $row) {
                $sheetXml .= "<row r=\"{$rIndex}\">";
                $cIndex = 'A';
                foreach ($row as $cellVal) {
                    $cellEscaped = htmlspecialchars((string) ($cellVal ?? ''));
                    $sheetXml .= "<c r=\"{$cIndex}{$rIndex}\" t=\"inlineStr\"><is><t>{$cellEscaped}</t></is></c>";
                    $cIndex++;
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
        } elseif ($type === 'presentation') {
            $slides = $draft['slides'] ?? [['title' => $title, 'body' => '']];
            $slide1 = $slides[0] ?? ['title' => $title, 'body' => ''];
            $slideTitle = htmlspecialchars($slide1['title'] ?? $title);
            $slideBody = htmlspecialchars($slide1['body'] ?? '');

            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
            $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>');
            $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst></p:presentation>');
            $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/><p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="ctrTitle"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' . $slideTitle . '</a:t></a:r></a:p></p:txBody></p:sp><p:sp><p:nvSpPr><p:cNvPr id="3" name="Subtitle"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="subTitle" idx="1"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' . $slideBody . '</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>');
        } else {
            // Document
            $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
            $paragraphs = $draft['paragraphs'] ?? [$title];
            foreach ($paragraphs as $para) {
                $escaped = htmlspecialchars(is_array($para) ? ($para['text'] ?? '') : (string) $para);
                $docXml .= "<w:p><w:r><w:t>{$escaped}</w:t></w:r></w:p>";
            }
            $docXml .= '</w:body></w:document>';

            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
            $zip->addFromString('word/document.xml', $docXml);
        }

        $zip->close();
        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Provide default JSON schema for new documents.
     */
    public function getDefaultDraftStructure(string $type, string $title): array
    {
        return match ($type) {
            'spreadsheet' => [
                'sheets' => ['Sheet1'],
                'activeSheet' => 'Sheet1',
                'rows' => [
                    ['A' => $title, 'B' => '', 'C' => '', 'D' => ''],
                    ['A' => 'Item', 'B' => 'Category', 'C' => 'Amount', 'D' => 'Notes'],
                    ['A' => 'Sample Data 1', 'B' => 'Operations', 'C' => '1500', 'D' => 'Q1'],
                    ['A' => 'Sample Data 2', 'B' => 'Marketing', 'C' => '2400', 'D' => 'Q2'],
                    ['A' => 'Total', 'B' => '', 'C' => '=SUM(C3:C4)', 'D' => ''],
                ],
            ],
            'presentation' => [
                'slides' => [
                    [
                        'id' => 1,
                        'title' => $title,
                        'subtitle' => 'Created in MyStorage Online Suite',
                        'notes' => 'Welcome slide notes.',
                        'background' => '#1e293b',
                    ],
                    [
                        'id' => 2,
                        'title' => 'Project Overview',
                        'subtitle' => 'Key milestones and deliverables',
                        'notes' => 'Discuss milestones with team.',
                        'background' => '#0f172a',
                    ],
                ],
            ],
            default => [
                'title' => $title,
                'paragraphs' => [
                    ['text' => $title, 'style' => 'h1'],
                    ['text' => 'Start typing your document here or collaborate with your team in real time.', 'style' => 'normal'],
                ],
            ],
        };
    }
}
