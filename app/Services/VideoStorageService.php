<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStorageService
{
    public function storeInputImage(UploadedFile $image): string
    {
        $path = $image->storeAs('videos/inputs', Str::uuid().'.'.$image->extension());

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan gambar unggahan.');
        }

        return $path;
    }

    /**
     * @return array{data: string, mime: string}|null
     */
    public function inputImage(Video $video): ?array
    {
        if ($video->input_image_path === null) {
            return null;
        }

        return [
            'data' => base64_encode(Storage::get($video->input_image_path)),
            'mime' => Storage::mimeType($video->input_image_path) ?: 'image/jpeg',
        ];
    }

    public function storeOutputVideo(Video $video, string $contents): string
    {
        $path = "videos/outputs/{$video->id}.mp4";

        Storage::put($path, $contents);

        return $path;
    }

    public function outputVideoResponse(Video $video): StreamedResponse
    {
        return Storage::response($video->output_video_path, "video-{$video->id}.mp4", ['Content-Type' => 'video/mp4']);
    }

    public function deleteFiles(Video $video): void
    {
        Storage::delete(array_filter([$video->input_image_path, $video->output_video_path]));
    }
}
