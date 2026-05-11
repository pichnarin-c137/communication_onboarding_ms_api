<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmMediaRequest;
use App\Http\Requests\PresignMediaRequest;
use App\Models\Media;
use App\Services\R2Service;
use Illuminate\Http\JsonResponse;

class MediaPresignController extends Controller
{
    public function __construct(
        private readonly R2Service $r2Service
    ) {}

    public function presign(PresignMediaRequest $request): JsonResponse
    {
        $result = $this->r2Service->generatePresignedUrl(
            $request->input('folder', 'uploads'),
            $request->input('filename'),
            $request->input('mime_type'),
        );

        if (! $result) {
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to generate upload URL. Check R2 configuration.',
                'error_code' => 'R2_PRESIGN_FAILED',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Presigned upload URL generated.',
            'data'    => $result,
        ]);
    }

    public function confirm(ConfirmMediaRequest $request): JsonResponse
    {
        $key = $request->input('key');

        if (! $this->r2Service->fileExists($key)) {
            return response()->json([
                'success'    => false,
                'message'    => 'File not found in storage. Upload the file before confirming.',
                'error_code' => 'R2_FILE_NOT_FOUND',
            ], 404);
        }

        $fileUrl  = $this->r2Service->publicUrl($key);
        $filename = basename($key);

        $media = Media::create([
            'filename'             => $filename,
            'original_filename'    => $request->input('original_filename', $filename),
            'file_url'             => $fileUrl,
            'file_size'            => $request->input('file_size'),
            'mime_type'            => $request->input('mime_type'),
            'media_category'       => $request->input('category', 'other'),
            'uploaded_by_user_id'  => $request->get('auth_user_id'),
            'cloudinary_public_id' => $key,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media confirmed and record created.',
            'data'    => [
                'media_id' => $media->id,
                'file_url' => $media->file_url,
                'filename' => $media->filename,
            ],
        ], 201);
    }
}
