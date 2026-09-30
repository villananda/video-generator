<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Http\Requests\StoreVideoRequest;
use App\Models\Video;
use App\Services\VideoService;
use App\Services\VideoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    public function __construct(private VideoService $videos) {}

    public function create(): Response
    {
        return Inertia::render('videos/Create');
    }

    public function store(StoreVideoRequest $request): RedirectResponse
    {
        $video = $this->videos->create($request->toData());

        return to_route('videos.show', $video);
    }

    public function show(Video $video): Response
    {
        return Inertia::render('videos/Show', [
            'video' => [
                ...$this->videos->status($video),
                'mode' => $video->mode->value,
                'prompt' => $video->prompt,
                'aspect_ratio' => $video->aspect_ratio->value,
                'resolution' => $video->resolution->value,
                'created_at' => $video->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function status(Video $video): JsonResponse
    {
        return response()->json($this->videos->status($video));
    }

    public function file(Video $video, VideoStorageService $storage): StreamedResponse
    {
        abort_unless($video->status === VideoStatus::Done && $video->output_video_path, 404);

        return $storage->outputVideoResponse($video);
    }

    public function retry(Video $video): RedirectResponse
    {
        $this->videos->retry($video);

        return to_route('videos.show', $video);
    }
}
