<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class R2Service
{
    public function generatePresignedUrl(string $folder, string $filename, string $mimeType, int $expiresIn = 300): ?array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: $this->mimeToExtension($mimeType);
        $key = $folder.'/'.Str::uuid().'.'.$extension;

        try {
            // temporaryUploadUrl() returns ['url' => string, 'headers' => array]
            $result = Storage::disk('r2')->temporaryUploadUrl(
                $key,
                now()->addSeconds($expiresIn),
                ['ContentType' => $mimeType]
            );

            return [
                'upload_url' => $result['url'],
                'headers'    => $result['headers'] ?? [],
                'key'        => $key,
            ];
        } catch (Throwable $e) {
            Log::error('R2Service: presigned URL generation failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function fileExists(string $key): bool
    {
        try {
            return Storage::disk('r2')->exists($key);
        } catch (Throwable $e) {
            Log::error('R2Service: fileExists check failed', ['key' => $key, 'message' => $e->getMessage()]);

            return false;
        }
    }

    public function publicUrl(string $key): string
    {
        return Storage::disk('r2')->url($key);
    }

    private function mimeToExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => explode('/', $mimeType)[1] ?? 'bin',
        };
    }
}
