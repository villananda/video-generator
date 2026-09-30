<?php

use App\Enums\Resolution;
use App\Enums\VideoErrorCode;
use App\Enums\VideoMode;
use App\Enums\VideoStatus;
use App\Exceptions\VideoGenerationException;
use App\Jobs\GenerateVideoJob;
use App\Models\Video;
use App\Services\VideoGenerationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const INTERACTIONS_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';
const FILE_URL = 'https://generativelanguage.googleapis.com/v1beta/files/abc-123';
const DOWNLOAD_URL = 'https://generativelanguage.googleapis.com/v1beta/files/abc-123:download?alt=media';

/**
 * @param  array<string, string>  $video
 * @return array<string, mixed>
 */
function interactionResponse(array $video): array
{
    return [
        'id' => 'v1_interaction',
        'status' => 'completed',
        'steps' => [
            ['type' => 'thought', 'content' => [['type' => 'thought', 'text' => '...']]],
            ['type' => 'model_output', 'content' => [['type' => 'video', 'mime_type' => 'video/mp4', ...$video]]],
        ],
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.gemini.key' => 'test-key']);
});

test('a small video is generated through inline base64 delivery', function () {
    Storage::fake();
    Http::fake([INTERACTIONS_URL => Http::response(interactionResponse(['data' => base64_encode('mp4-bytes')]))]);
    $video = Video::factory()->create(['resolution' => Resolution::P720, 'final_prompt' => 'A cat']);

    app(VideoGenerationService::class)->process($video);

    expect($video->fresh())
        ->status->toBe(VideoStatus::Done)
        ->interaction_id->toBe('v1_interaction')
        ->attempts->toBe(1);
    Storage::assertExists($video->fresh()->output_video_path);
    expect(Storage::get($video->fresh()->output_video_path))->toBe('mp4-bytes');
    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key')
        && $request['input'] === 'A cat'
        && $request['response_format'] === ['type' => 'video', 'aspect_ratio' => '16:9', 'resolution' => '720p', 'delivery' => 'base64']);
});

test('an upscaled video is downloaded through the uri once the file is active', function () {
    Storage::fake();
    Sleep::fake();
    Http::fake([
        INTERACTIONS_URL => Http::response(interactionResponse(['uri' => DOWNLOAD_URL])),
        DOWNLOAD_URL => Http::response('large-mp4-bytes'),
        FILE_URL => Http::sequence()->push(['state' => 'PROCESSING'])->push(['state' => 'ACTIVE']),
    ]);
    $video = Video::factory()->create(['resolution' => Resolution::P1080]);

    app(VideoGenerationService::class)->process($video);

    expect($video->fresh()->status)->toBe(VideoStatus::Done)
        ->and(Storage::get($video->fresh()->output_video_path))->toBe('large-mp4-bytes');
    Http::assertSent(fn (Request $request) => $request->url() === INTERACTIONS_URL && $request['response_format']['delivery'] === 'uri');
    Sleep::assertSleptTimes(1);
});

test('an image video sends the image as the first frame', function () {
    Storage::fake();
    Storage::put('videos/inputs/photo.png', 'png-bytes');
    Http::fake([INTERACTIONS_URL => Http::response(interactionResponse(['data' => base64_encode('mp4-bytes')]))]);
    $video = Video::factory()->create([
        'mode' => VideoMode::Image,
        'final_prompt' => '<FIRST_FRAME> Rotate the product',
        'input_image_path' => 'videos/inputs/photo.png',
    ]);

    app(VideoGenerationService::class)->process($video);

    Http::assertSent(fn (Request $request) => $request['input'][0] === ['type' => 'image', 'data' => base64_encode('png-bytes'), 'mime_type' => 'image/png']
        && $request['input'][1] === ['type' => 'text', 'text' => '<FIRST_FRAME> Rotate the product']
        && $request['generation_config']['video_config']['task'] === 'image_to_video');
});

test('non retryable api errors mark the video as failed', function (mixed $response, VideoErrorCode $errorCode) {
    Http::fake([INTERACTIONS_URL => $response]);
    $video = Video::factory()->create();

    app(VideoGenerationService::class)->process($video);

    expect($video->fresh())
        ->status->toBe(VideoStatus::Failed)
        ->error_code->toBe($errorCode);
})->with([
    'filtered output' => fn () => [Http::response(['id' => 'v1_x', 'status' => 'completed', 'steps' => []]), VideoErrorCode::SafetyBlocked],
    'blocked input' => fn () => [Http::response(['error' => ['message' => 'Request blocked by safety filters']], 400), VideoErrorCode::SafetyBlocked],
    'invalid request' => fn () => [Http::response(['error' => ['message' => 'Invalid argument']], 400), VideoErrorCode::InvalidInput],
]);

test('retryable api errors are rethrown so the queue retries', function (int $status, VideoErrorCode $errorCode) {
    Http::fake([INTERACTIONS_URL => Http::response(['error' => ['message' => 'nope']], $status)]);
    $video = Video::factory()->create();

    expect(fn () => app(VideoGenerationService::class)->process($video))
        ->toThrow(fn (VideoGenerationException $exception) => expect($exception->errorCode)->toBe($errorCode));
    expect($video->fresh()->status)->toBe(VideoStatus::Processing);
})->with([
    'quota' => [429, VideoErrorCode::QuotaExceeded],
    'server error' => [503, VideoErrorCode::UpstreamError],
]);

test('a job that exhausts its retries marks the video with the last error', function () {
    $video = Video::factory()->processing()->create();

    (new GenerateVideoJob($video))->failed(new VideoGenerationException(VideoErrorCode::QuotaExceeded));

    expect($video->fresh())
        ->status->toBe(VideoStatus::Failed)
        ->error_code->toBe(VideoErrorCode::QuotaExceeded)
        ->error_message->toBe(VideoErrorCode::QuotaExceeded->message());
});
