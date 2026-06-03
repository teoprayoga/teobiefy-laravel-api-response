<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use InvalidArgumentException;

class Profile
{
    public const PLAIN = 'plain';

    public const COMPRESSED = 'compressed';

    public const ENCRYPTED = 'encrypted';

    public const COMPRESSED_ENCRYPTED = 'compressed_encrypted';

    public const SIGNED = 'signed';

    public const COMPRESSED_SIGNED = 'compressed_signed';

    public function __construct(private readonly string $name)
    {
        if (! in_array($name, self::names(), true)) {
            throw new InvalidArgumentException("Unsupported API payload profile [{$name}].");
        }
    }

    public static function from(?string $name): self
    {
        return new self($name ?: self::PLAIN);
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            self::PLAIN,
            self::COMPRESSED,
            self::ENCRYPTED,
            self::COMPRESSED_ENCRYPTED,
            self::SIGNED,
            self::COMPRESSED_SIGNED,
        ];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function compresses(): bool
    {
        return in_array($this->name, [self::COMPRESSED, self::COMPRESSED_ENCRYPTED, self::COMPRESSED_SIGNED], true);
    }

    public function encrypts(): bool
    {
        return in_array($this->name, [self::ENCRYPTED, self::COMPRESSED_ENCRYPTED], true);
    }

    public function signs(): bool
    {
        return in_array($this->name, [self::SIGNED, self::COMPRESSED_SIGNED], true);
    }

    public function isPlain(): bool
    {
        return $this->name === self::PLAIN;
    }
}
