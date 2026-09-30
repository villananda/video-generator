<?php

namespace App\Services;

use App\Contracts\VideoProviderInterface;
use App\DTOs\GenerationRequestData;
use App\Enums\VideoErrorCode;
use App\Enums\VideoStatus;
use App\Exceptions\VideoGenerationException;
use App\Models\Video;
use Illuminate\Support\Facades\Log;

class VideoGenerationService
{
    public function __construct(
        private VideoProviderInterface $provider,
        private VideoStorageService $storage,
    ) {}

    /**
     * Retryable errors are rethrown so the queue can back off and try again.
     *
     * @throws VideoGenerationException
     */
    public function process(Video $video): void
    {
        if ($video->status->isFinal()) {
            return;
        }

        $video->update([
            'status' => VideoStatus::Processing,
            'started_at' => now(),
            'attempts' => $video->attempts + 1,
        ]);

        $image = $this->storage->inputImage($video);
        $startedAt = microtime(true);

        try {
            $result = $this->provider->generate(new GenerationRequestData(
                finalPrompt: $video->final_prompt,
                aspectRatio: $video->aspect_ratio,
                resolution: $video->resolution,
                imageBase64: $image['data'] ?? null,
                imageMime: $image['mime'] ?? null,
            ));
        } catch (VideoGenerationException $exception) {
            Log::warning('Video generation failed', [
                'video_id' => $video->id,
                'error_code' => $exception->errorCode->value,
                'attempt' => $video->attempts,
            ]);

            if ($exception->errorCode->isRetryable()) {
                throw $exception;
            }

            $this->markFailed($video, $exception->errorCode, $exception->getMessage());

            return;
        }

        $video->update([
            'status' => VideoStatus::Done,
            'interaction_id' => $result->interactionId,
            'output_video_path' => $this->storage->storeOutputVideo($video, $result->videoContents),
            'finished_at' => now(),
        ]);

        Log::info('Video generated', [
            'video_id' => $video->id,
            'resolution' => $video->resolution->value,
            'duration_seconds' => round(microtime(true) - $startedAt, 1),
        ]);
    }

    public function markFailed(Video $video, VideoErrorCode $errorCode, ?string $message = null): void
    {
        $video->update([
            'status' => VideoStatus::Failed,
            'error_code' => $errorCode,
            'error_message' => $message ?: $errorCode->message(),
            'finished_at' => now(),
        ]);
    }
}
