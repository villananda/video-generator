<?php

namespace App\Services;

use App\DTOs\CreateVideoData;
use App\Enums\VideoErrorCode;
use App\Enums\VideoStatus;
use App\Jobs\GenerateVideoJob;
use App\Models\Video;
use Illuminate\Validation\ValidationException;

class VideoService
{
    /**
     * Videos stuck in processing longer than this are marked as timed out.
     */
    public const int STUCK_AFTER_MINUTES = 20;

    public function __construct(
        private PromptBuilderService $promptBuilder,
        private VideoStorageService $storage,
    ) {}

    public function create(CreateVideoData $data): Video
    {
        $video = Video::create([
            'user_id' => $data->userId,
            'session_id' => $data->sessionId,
            'mode' => $data->mode,
            'prompt' => $data->prompt,
            'final_prompt' => $this->promptBuilder->build($data),
            'aspect_ratio' => $data->aspectRatio,
            'resolution' => $data->resolution,
            'status' => VideoStatus::Queued,
            'input_image_path' => $data->image ? $this->storage->storeInputImage($data->image) : null,
            'expires_at' => now()->addDays(config('services.videos.retention_days')),
        ]);

        GenerateVideoJob::dispatch($video);

        return $video;
    }

    /**
     * @throws ValidationException
     */
    public function retry(Video $video): Video
    {
        if ($video->status !== VideoStatus::Failed || $video->error_code === VideoErrorCode::SafetyBlocked) {
            throw ValidationException::withMessages([
                'video' => 'Video ini tidak dapat dicoba lagi. Ubah prompt lalu buat video baru.',
            ]);
        }

        $video->update([
            'status' => VideoStatus::Queued,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        GenerateVideoJob::dispatch($video);

        return $video;
    }

    /**
     * @return array{id: string, status: string, video_url: ?string, error_code: ?string, error_message: ?string, can_retry: bool, queue_position: ?int}
     */
    public function status(Video $video): array
    {
        return [
            'id' => $video->id,
            'status' => $video->status->value,
            'video_url' => $video->status === VideoStatus::Done ? route('videos.file', $video) : null,
            'error_code' => $video->error_code?->value,
            'error_message' => $video->error_message,
            'can_retry' => $video->status === VideoStatus::Failed && $video->error_code !== VideoErrorCode::SafetyBlocked,
            'queue_position' => $video->status === VideoStatus::Queued
                ? Video::where('status', VideoStatus::Queued)->where('created_at', '<', $video->created_at)->count() + 1
                : null,
        ];
    }

    /**
     * Mark videos whose worker died mid-generation as failed.
     */
    public function failStuckVideos(): int
    {
        return Video::where('status', VideoStatus::Processing)
            ->where('started_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->update([
                'status' => VideoStatus::Failed,
                'error_code' => VideoErrorCode::Timeout,
                'error_message' => VideoErrorCode::Timeout->message(),
                'finished_at' => now(),
            ]);
    }
}
