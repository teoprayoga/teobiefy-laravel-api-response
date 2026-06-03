<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Exceptions;

use RuntimeException;
use Throwable;

class InvalidPayloadException extends RuntimeException
{
    public static function because(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
