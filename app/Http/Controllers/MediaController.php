<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadMediaRequest;
use App\Models\Media;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService
    ) {}

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $base64 = $request->input('base64');
        $category = $request->input('category', 'other');

        // 1. Try Cloudinary first
        $cloudinaryData = $this->cloudinaryService->upload($file ?: $base64, $category);

        if ($cloudinaryData) {
            $media = Media::create([
                'filename' => basename($cloudinaryData['url']),
                'original_filename' => $file ? $file->getClientOriginalName() : 'base64_image.png',
                'file_url' => $cloudinaryData['url'],
                'file_size' => $cloudinaryData['size'],
                'mime_type' => $cloudinaryData['mime_type'],
                'media_category' => $category,
                'uploaded_by_user_id' => $request->get('auth_user_id'),
                'cloudinary_public_id' => $cloudinaryData['public_id'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->mediaResponse($media, 'cloudinary'),
            ], 201);
        }

        // 2. Fallback to Local Storage (only works for physical files)
        if (! $file) {
            return response()->json([
                'success' => false,
                'message' => 'Cloudinary upload failed and no physical file provided for local fallback.',
                'error_code' => 'CLOUDINARY_UPLOAD_FAILED',
            ], 400);
        }

        $storedPath = Storage::disk('public')->putFile('proofs', $file);
        $fileUrl = Storage::disk('public')->url($storedPath);

        try {
            $media = Media::create([
                'filename' => basename($storedPath),
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_url' => $fileUrl,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'media_category' => $category,
                'uploaded_by_user_id' => $request->get('auth_user_id'),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $this->mediaResponse($media, 'local'),
        ], 201);
    }

    private function mediaResponse(Media $media, string $storage): array
    {
        return [
            'id' => $media->id,
            'file_url' => $media->file_url,
            'filename' => $media->filename,
            'media_category' => $media->media_category,
            'storage' => $storage,
        ];
    }
}
