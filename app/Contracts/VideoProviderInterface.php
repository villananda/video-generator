<?php

namespace App\Contracts;

use App\DTOs\GenerationRequestData;
use App\DTOs\GenerationResultData;
use App\Exceptions\VideoGenerationException;

interface VideoProviderInterface
{
    /**
     * @throws VideoGenerationException
     */
    public function generate(GenerationRequestData $request): GenerationResultData;
}
