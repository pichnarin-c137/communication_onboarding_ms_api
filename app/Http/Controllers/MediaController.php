<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadMediaRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $category = $request->input('category', 'other');

        $storedPath = Storage::disk('public')->putFile('proofs', $file);
        $fileUrl = Storage::disk('public')->url($storedPath);

        try {
            $media = Media::create([
                'filename'            => basename($storedPath),
                'original_filename'   => $file->getClientOriginalName(),
                'file_path'           => $storedPath,
                'file_url'            => $fileUrl,
                'file_size'           => $file->getSize(),
                'mime_type'           => $file->getMimeType(),
                'media_category'      => $category,
                'uploaded_by_user_id' => $request->get('auth_user_id'),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $media->id,
                'file_url'       => $media->file_url,
                'filename'       => $media->filename,
                'media_category' => $media->media_category,
            ],
        ], 201);
    }
}
