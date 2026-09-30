<?php

namespace App\Exceptions;

use App\Enums\VideoErrorCode;
use RuntimeException;
use Throwable;

class VideoGenerationException extends RuntimeException
{
    public function __construct(
        public readonly VideoErrorCode $errorCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?: $errorCode->message(), previous: $previous);
    }
}
