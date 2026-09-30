<?php

use App\Enums\VideoErrorCode;
use App\Enums\VideoStatus;
use App\Jobs\GenerateVideoJob;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function validVideoInput(array $overrides = []): array
{
    return [
        'mode' => 'text',
        'prompt' => 'A kitten chasing yarn',
        'aspect_ratio' => '9:16',
        'resolution' => '360p',
        ...$overrides,
    ];
}

test('the home page renders the create video page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('videos/Create'));
});

test('a text video is queued with toggle instructions appended to the prompt', function () {
    Queue::fake([GenerateVideoJob::class]);

    $response = $this->post(route('videos.store'), validVideoInput([
        'single_scene' => '1',
        'no_dialogue' => '1',
        'background_music' => '1',
    ]));

    $video = Video::sole();
    $response->assertRedirect(route('videos.show', $video));
    expect($video->status)->toBe(VideoStatus::Queued)
        ->and($video->final_prompt)->toBe('A kitten chasing yarn In a single continuous shot. No scene cuts. No dialogue. Include calm background music.')
        ->and($video->input_image_path)->toBeNull();
    Queue::assertPushed(GenerateVideoJob::class, fn (GenerateVideoJob $job) => $job->video->is($video));
});

test('an image video stores the upload and marks it as the first frame', function () {
    Storage::fake();
    Queue::fake([GenerateVideoJob::class]);

    $this->post(route('videos.store'), validVideoInput([
        'mode' => 'image',
        'image' => UploadedFile::fake()->image('product.png'),
    ]))->assertSessionHasNoErrors();

    $video = Video::sole();
    expect($video->final_prompt)->toBe('<FIRST_FRAME> A kitten chasing yarn');
    Storage::assertExists($video->input_image_path);
});

test('invalid video input is rejected', function (array $input, string $field) {
    Queue::fake([GenerateVideoJob::class]);

    $this->post(route('videos.store'), validVideoInput($input))->assertSessionHasErrors($field);

    Queue::assertNotPushed(GenerateVideoJob::class);
})->with([
    'missing prompt' => [['prompt' => ''], 'prompt'],
    'prompt too long' => [['prompt' => str_repeat('a', 1001)], 'prompt'],
    'image mode without image' => [['mode' => 'image'], 'image'],
    'non image file' => [['mode' => 'image', 'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], 'image'],
    'image over 10 MB' => [['mode' => 'image', 'image' => UploadedFile::fake()->image('big.png')->size(10241)], 'image'],
    'unsupported resolution' => [['resolution' => '4k'], 'resolution'],
    'unsupported aspect ratio' => [['aspect_ratio' => '1:1'], 'aspect_ratio'],
]);

test('video creation is rate limited per ip', function () {
    Queue::fake([GenerateVideoJob::class]);

    foreach (range(1, 3) as $attempt) {
        $this->post(route('videos.store'), validVideoInput())->assertSessionHasNoErrors();
    }

    $this->post(route('videos.store'), validVideoInput())
        ->assertSessionHasErrors(['prompt' => VideoErrorCode::RateLimited->message()]);

    expect(Video::count())->toBe(3);
});

test('the show page renders the video status', function () {
    $video = Video::factory()->create();

    $this->get(route('videos.show', $video))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('videos/Show')
            ->where('video.id', $video->id)
            ->where('video.status', 'queued'));
});

test('the status endpoint reports the queue position', function () {
    Video::factory()->create(['created_at' => now()->subMinute()]);
    $video = Video::factory()->create();

    $this->getJson(route('videos.status', $video))
        ->assertOk()
        ->assertJson(['status' => 'queued', 'queue_position' => 2, 'video_url' => null]);
});

test('the status endpoint exposes the file url once done', function () {
    $video = Video::factory()->done()->create();

    $this->getJson(route('videos.status', $video))
        ->assertJson(['status' => 'done', 'video_url' => route('videos.file', $video)]);
});

test('the status endpoint exposes the error of a failed video', function () {
    $video = Video::factory()->failed(VideoErrorCode::SafetyBlocked)->create();

    $this->getJson(route('videos.status', $video))
        ->assertJson([
            'status' => 'failed',
            'error_code' => 'SAFETY_BLOCKED',
            'error_message' => VideoErrorCode::SafetyBlocked->message(),
            'can_retry' => false,
        ]);
});

test('a finished video file can be streamed', function () {
    Storage::fake();
    $video = Video::factory()->done()->create();
    Storage::put($video->output_video_path, 'mp4-bytes');

    $response = $this->get(route('videos.file', $video));

    $response->assertOk()->assertHeader('Content-Type', 'video/mp4');
    expect($response->streamedContent())->toBe('mp4-bytes');
});

test('an unfinished video file is not found', function () {
    $video = Video::factory()->processing()->create();

    $this->get(route('videos.file', $video))->assertNotFound();
});

test('a failed video can be retried', function () {
    Queue::fake([GenerateVideoJob::class]);
    $video = Video::factory()->failed(VideoErrorCode::UpstreamError)->create();

    $this->post(route('videos.retry', $video))->assertRedirect(route('videos.show', $video));

    expect($video->fresh())
        ->status->toBe(VideoStatus::Queued)
        ->error_code->toBeNull();
    Queue::assertPushed(GenerateVideoJob::class);
});

test('a safety blocked video cannot be retried', function () {
    Queue::fake([GenerateVideoJob::class]);
    $video = Video::factory()->failed(VideoErrorCode::SafetyBlocked)->create();

    $this->post(route('videos.retry', $video))->assertSessionHasErrors('video');

    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
    Queue::assertNotPushed(GenerateVideoJob::class);
});

test('videos stuck in processing are marked as timed out', function () {
    $stuck = Video::factory()->processing()->create(['started_at' => now()->subMinutes(VideoService::STUCK_AFTER_MINUTES + 1)]);
    $active = Video::factory()->processing()->create();

    app(VideoService::class)->failStuckVideos();

    expect($stuck->fresh())
        ->status->toBe(VideoStatus::Failed)
        ->error_code->toBe(VideoErrorCode::Timeout)
        ->and($active->fresh()->status)->toBe(VideoStatus::Processing);
});

test('expired videos are pruned together with their files', function () {
    Storage::fake();
    $expired = Video::factory()->done()->create(['expires_at' => now()->subMinute()]);
    $kept = Video::factory()->done()->create();
    Storage::put($expired->output_video_path, 'mp4-bytes');

    $this->artisan('model:prune', ['--model' => Video::class])->assertSuccessful();

    expect(Video::find($expired->id))->toBeNull()
        ->and(Video::find($kept->id))->not->toBeNull();
    Storage::assertMissing($expired->output_video_path);
});
