<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
