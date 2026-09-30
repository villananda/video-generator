<?php

namespace App\DTOs;

use App\Enums\AspectRatio;
use App\Enums\Resolution;

final readonly class GenerationRequestData
{
    public function __construct(
        public string $finalPrompt,
        public AspectRatio $aspectRatio,
        public Resolution $resolution,
        public ?string $imageBase64 = null,
        public ?string $imageMime = null,
    ) {}
}
