<?php

namespace Database\Factories;

use App\Enums\AspectRatio;
use App\Enums\Resolution;
use App\Enums\VideoErrorCode;
use App\Enums\VideoMode;
use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prompt = fake()->sentence();

        return [
            'session_id' => fake()->uuid(),
            'mode' => VideoMode::Text,
            'prompt' => $prompt,
            'final_prompt' => $prompt,
            'aspect_ratio' => AspectRatio::Landscape,
            'resolution' => Resolution::P720,
            'status' => VideoStatus::Queued,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => VideoStatus::Processing,
            'started_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VideoStatus::Done,
            'output_video_path' => 'videos/outputs/'.fake()->uuid().'.mp4',
            'finished_at' => now(),
        ]);
    }

    public function failed(VideoErrorCode $errorCode = VideoErrorCode::UpstreamError): static
    {
        return $this->state(fn (): array => [
            'status' => VideoStatus::Failed,
            'error_code' => $errorCode,
            'error_message' => $errorCode->message(),
            'finished_at' => now(),
        ]);
    }
}
