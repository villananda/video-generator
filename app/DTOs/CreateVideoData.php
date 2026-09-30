<?php

namespace App\DTOs;

use App\Enums\AspectRatio;
use App\Enums\Resolution;
use App\Enums\VideoMode;
use Illuminate\Http\UploadedFile;

final readonly class CreateVideoData
{
    public function __construct(
        public VideoMode $mode,
        public string $prompt,
        public AspectRatio $aspectRatio,
        public Resolution $resolution,
        public bool $singleScene = false,
        public bool $noDialogue = false,
        public bool $backgroundMusic = false,
        public ?UploadedFile $image = null,
        public ?string $sessionId = null,
        public ?int $userId = null,
    ) {}
}
