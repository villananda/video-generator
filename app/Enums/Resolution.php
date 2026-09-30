<?php

namespace App\Enums;

enum Resolution: string
{
    case P360 = '360p';
    case P720 = '720p';
    case P1080 = '1080p';

    /**
     * Upscaled output is usually larger than 4 MB, so it must be fetched via URI.
     */
    public function needsUriDelivery(): bool
    {
        return $this === self::P1080;
    }

    public function badge(): ?string
    {
        return match ($this) {
            self::P360 => 'Hemat',
            self::P720 => null,
            self::P1080 => 'Upscaled',
        };
    }
}
