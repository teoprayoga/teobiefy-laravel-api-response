<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Exceptions;

class InvalidSignatureException extends InvalidPayloadException
{
    public static function because(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
