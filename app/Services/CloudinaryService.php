<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    private ?string $cloudName;
    private ?string $uploadPreset;

    public function __construct()
    {
        $url = config('services.cloudinary.cloud_url');
        if ($url) {
            // cloudinary://API_KEY:API_SECRET@CLOUD_NAME
            $parts = parse_url($url);
            $this->cloudName = $parts['host'] ?? null;
        } else {
            $this->cloudName = null;
        }
        
        $this->uploadPreset = config('services.cloudinary.upload_preset');
    }

    /**
     * Upload an image to Cloudinary.
     * 
     * @param string|UploadedFile $file Either a Base64 string or an UploadedFile instance.
     * @param string $folder
     * @return array|null
     */
    public function upload($file, string $folder = 'proofs'): ?array
    {
        if (!$this->cloudName || !$this->uploadPreset) {
            Log::warning('Cloudinary service not configured properly. Ensure CLOUDINARY_URL and CLOUDINARY_UPLOAD_PRESET are set.');
            return null;
        }

        $endpoint = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        $payload = [
            'upload_preset' => $this->uploadPreset,
            'folder' => $folder,
        ];

        try {
            if ($file instanceof UploadedFile) {
                $response = Http::attach(
                    'file', 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )->post($endpoint, $payload);
            } else {
                // Assume Base64 string
                $response = Http::asMultipart()->post($endpoint, array_merge($payload, [
                    'file' => $file,
                ]));
            }

            if ($response->failed()) {
                Log::error('Cloudinary upload failed', [
                    'status' => $response->status(),
                    'error_body' => $response->body(),
                    'endpoint' => $endpoint,
                    'preset' => $this->uploadPreset,
                ]);
                return null;
            }

            $data = $response->json();

            return [
                'url' => $data['secure_url'] ?? $data['url'],
                'public_id' => $data['public_id'],
                'size' => $data['bytes'] ?? 0,
                'mime_type' => ($data['resource_type'] ?? 'image') . '/' . ($data['format'] ?? 'jpg'),
            ];
        } catch (\Throwable $e) {
            Log::error('CloudinaryService exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
