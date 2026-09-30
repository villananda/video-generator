<?php

namespace App\DTOs;

final readonly class GenerationResultData
{
    /**
     * @param  string  $videoContents  Raw MP4 bytes.
     */
    public function __construct(
        public string $interactionId,
        public string $videoContents,
    ) {}
}
