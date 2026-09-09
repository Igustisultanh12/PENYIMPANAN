<?php

namespace App\Services\Security;

class FileSecurityValidator
{
    /**
     * Dangerous executable extensions strictly forbidden from being executed or served directly.
     */
    protected array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'dll', 'so', 'bat', 'cmd', 'sh', 'bash', 'vbs', 'ps1',
        'jar', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py'
    ];

    /**
     * Common magic bytes signatures for validation.
     */
    protected array $magicBytesSignatures = [
        'jpg'  => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif'  => ["GIF87a", "GIF89a"],
        'pdf'  => ["%PDF-"],
        'zip'  => ["PK\x03\x04"],
    ];

    /**
     * Check if a file extension is blocked.
     */
    public function isBlockedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->blockedExtensions, true);
    }

    /**
     * Validate file header magic bytes against expected extension.
     */
    public function matchesMagicBytes(string $filePath, string $extension): bool
    {
        $ext = strtolower($extension);

        if (!isset($this->magicBytesSignatures[$ext])) {
            return true; // No explicit signature required for general files
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        foreach ($this->magicBytesSignatures[$ext] as $magic) {
            if (str_starts_with($header, $magic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inspect file and return validation result.
     *
     * @return array{safe: bool, reason: ?string, quarantine: bool}
     */
    public function inspect(string $filePath, string $filename, string $clientMime): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if ($this->isBlockedExtension($extension)) {
            return [
                'safe' => false,
                'reason' => "The extension .{$extension} is restricted for security reasons.",
                'quarantine' => true,
            ];
        }

        if (!$this->matchesMagicBytes($filePath, $extension)) {
            return [
                'safe' => false,
                'reason' => "File content does not match the expected signature for .{$extension}.",
                'quarantine' => true,
            ];
        }

        return [
            'safe' => true,
            'reason' => null,
            'quarantine' => false,
        ];
    }
}
