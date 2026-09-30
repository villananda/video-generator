<?php

namespace App\Services;

use App\DTOs\CreateVideoData;
use App\Enums\VideoMode;

class PromptBuilderService
{
    public function build(CreateVideoData $data): string
    {
        return collect([
            $data->mode === VideoMode::Image ? '<FIRST_FRAME>' : null,
            trim($data->prompt),
            $data->singleScene ? 'In a single continuous shot. No scene cuts.' : null,
            $data->noDialogue ? 'No dialogue.' : null,
            $data->backgroundMusic ? 'Include calm background music.' : null,
        ])->filter()->implode(' ');
    }
}
