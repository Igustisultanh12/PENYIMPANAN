<?php

namespace App\Jobs;

use App\Models\FileItem;
use App\Models\FileMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public FileItem $file) {}

    public function handle(): void
    {
        $file = $this->file;

        if ($file->category !== 'image') {
            return;
        }

        $fullPath = storage_path("app/{$file->storage_path}");
        if (!file_exists($fullPath)) {
            return;
        }

        $width = null;
        $height = null;
        $thumbnailPath = null;

        // Extract dimensions safely using getimagesize()
        $imageInfo = @getimagesize($fullPath);
        if ($imageInfo) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
        }

        // Generate thumbnail if GD extension is loaded
        if (extension_loaded('gd') && $width && $height) {
            try {
                $thumbDir = storage_path("app/thumbnails/" . dirname($file->storage_path));
                if (!is_dir($thumbDir)) {
                    mkdir($thumbDir, 0755, true);
                }

                $thumbFilename = "thumb_" . $file->uuid . ".webp";
                $thumbFullPath = "{$thumbDir}/{$thumbFilename}";
                $thumbRelativePath = "thumbnails/" . dirname($file->storage_path) . "/{$thumbFilename}";

                $targetWidth = 320;
                $targetHeight = (int) round(($height / $width) * $targetWidth);

                $srcImg = match (strtolower($file->extension)) {
                    'jpg', 'jpeg' => @imagecreatefromjpeg($fullPath),
                    'png' => @imagecreatefrompng($fullPath),
                    'webp' => @imagecreatefromwebp($fullPath),
                    default => null,
                };

                if ($srcImg) {
                    $thumbImg = imagecreatetruecolor($targetWidth, $targetHeight);
                    imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                    imagewebp($thumbImg, $thumbFullPath, 80);
                    imagedestroy($thumbImg);
                    imagedestroy($srcImg);

                    $thumbnailPath = $thumbRelativePath;
                }
            } catch (\Throwable $e) {
                Log::warning("Could not generate thumbnail for file {$file->uuid}: " . $e->getMessage());
            }
        }

        FileMetadata::updateOrCreate(
            ['file_id' => $file->id],
            [
                'width' => $width,
                'height' => $height,
                'thumbnail_path' => $thumbnailPath,
                'extra_attributes' => [
                    'color_depth' => $imageInfo['bits'] ?? null,
                    'channels' => $imageInfo['channels'] ?? null,
                ],
            ]
        );
    }
}
