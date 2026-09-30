<?php

namespace App\Providers\Gemini;

use App\Contracts\VideoProviderInterface;
use App\DTOs\GenerationRequestData;
use App\DTOs\GenerationResultData;
use App\Enums\VideoErrorCode;
use App\Exceptions\VideoGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * The only class that knows the Gemini Interactions API format.
 *
 * @see https://ai.google.dev/gemini-api/docs/omni
 */
class GeminiOmniProvider implements VideoProviderInterface
{
    private const int FILE_POLL_SECONDS = 5;

    private const int FILE_POLL_MAX_ATTEMPTS = 60;

    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl,
    ) {}

    public function generate(GenerationRequestData $request): GenerationResultData
    {
        $response = $this->send(fn (PendingRequest $http) => $http->timeout(300)->post('interactions', $this->payload($request)));

        $video = $response->collect('steps')
            ->where('type', 'model_output')
            ->flatMap(fn (array $step): array => $step['content'] ?? [])
            ->firstWhere('type', 'video');

        if ($video === null) {
            // A completed interaction without video output means the output was filtered.
            $errorCode = $response->json('status') === 'completed' ? VideoErrorCode::SafetyBlocked : VideoErrorCode::UpstreamError;

            throw new VideoGenerationException($errorCode);
        }

        $contents = isset($video['data'])
            ? base64_decode($video['data'], true)
            : $this->downloadFile($video['uri']);

        if (! is_string($contents) || $contents === '') {
            throw new VideoGenerationException(VideoErrorCode::UpstreamError, 'Video yang diterima kosong atau rusak.');
        }

        return new GenerationResultData((string) $response->json('id'), $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GenerationRequestData $request): array
    {
        $payload = [
            'model' => $this->model,
            'input' => $request->imageBase64
                ? [
                    ['type' => 'image', 'data' => $request->imageBase64, 'mime_type' => $request->imageMime],
                    ['type' => 'text', 'text' => $request->finalPrompt],
                ]
                : $request->finalPrompt,
            'response_format' => [
                'type' => 'video',
                'aspect_ratio' => $request->aspectRatio->value,
                'resolution' => $request->resolution->value,
                'delivery' => $request->resolution->needsUriDelivery() ? 'uri' : 'base64',
            ],
            // ponytail: store=false blocks previous_interaction_id edits; flip to true when F13 lands.
            'store' => false,
        ];

        if ($request->imageBase64) {
            $payload['generation_config'] = ['video_config' => ['task' => 'image_to_video']];
        }

        return $payload;
    }

    /**
     * Wait until the generated file is ACTIVE, then download it.
     */
    private function downloadFile(string $uri): string
    {
        $fileId = Str::before(Str::after($uri, '/files/'), ':');

        for ($attempt = 0; $attempt < self::FILE_POLL_MAX_ATTEMPTS; $attempt++) {
            $state = $this->send(fn (PendingRequest $http) => $http->get("files/{$fileId}"))->json('state');

            if ($state === 'ACTIVE') {
                return $this->send(fn (PendingRequest $http) => $http->timeout(120)->get($uri))->body();
            }

            if ($state === 'FAILED') {
                throw new VideoGenerationException(VideoErrorCode::UpstreamError, 'File video gagal diproses oleh layanan AI.');
            }

            Sleep::for(self::FILE_POLL_SECONDS)->seconds();
        }

        throw new VideoGenerationException(VideoErrorCode::Timeout);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            return $call(Http::baseUrl($this->baseUrl)->withHeaders(['x-goog-api-key' => $this->apiKey])->acceptJson())->throw();
        } catch (ConnectionException $exception) {
            throw new VideoGenerationException(VideoErrorCode::Timeout, previous: $exception);
        } catch (RequestException $exception) {
            throw new VideoGenerationException($this->mapError($exception->response), previous: $exception);
        }
    }

    private function mapError(Response $response): VideoErrorCode
    {
        $message = Str::lower((string) $response->json('error.message').' '.$response->json('error.status'));

        return match (true) {
            $response->status() === 429 => VideoErrorCode::QuotaExceeded,
            $response->serverError() => VideoErrorCode::UpstreamError,
            Str::contains($message, ['safety', 'blocked', 'prohibited', 'policy']) => VideoErrorCode::SafetyBlocked,
            in_array($response->status(), [400, 422], true) => VideoErrorCode::InvalidInput,
            default => VideoErrorCode::Internal,
        };
    }
}
