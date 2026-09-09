<?php

namespace App\Services\Storage;

use DateTimeInterface;

interface StorageDriverInterface
{
    public function put(string $path, mixed $contents, array $options = []): bool;

    public function putStream(string $path, $resource, array $options = []): bool;

    public function get(string $path): ?string;

    public function readStream(string $path);

    public function delete(string $path): bool;

    public function exists(string $path): bool;

    public function size(string $path): int;

    public function temporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string;

    public function mimeType(string $path): ?string;

    public function checksum(string $path): string;
}
