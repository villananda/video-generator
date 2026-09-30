<?php

namespace App\Jobs;

use App\Enums\VideoErrorCode;
use App\Exceptions\VideoGenerationException;
use App\Models\Video;
use App\Services\VideoGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

class GenerateVideoJob implements ShouldQueue
{
    use Queueable;

    /**
     * Must exceed the HTTP timeout plus file polling; keep below the queue's retry_after.
     */
    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public Video $video) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->video->id))->releaseAfter(60)->expireAfter($this->timeout + 60)];
    }

    public function handle(VideoGenerationService $generation): void
    {
        $generation->process($this->video);
    }

    public function failed(?Throwable $exception): void
    {
        $errorCode = match (true) {
            $exception instanceof VideoGenerationException => $exception->errorCode,
            $exception instanceof TimeoutExceededException => VideoErrorCode::Timeout,
            default => VideoErrorCode::Internal,
        };

        app(VideoGenerationService::class)->markFailed($this->video->fresh() ?? $this->video, $errorCode);
    }
}
