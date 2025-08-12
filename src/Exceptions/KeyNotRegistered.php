<?php

namespace Neon\Migration\Checksum\Exceptions;

use Exception;

class KeyNotRegistered extends Exception
{
    /**
     * @throws KeyNotRegistered
     */
    public static function throw(string $key): void
    {
        throw new self("Checksum key '$key' is not registered.");
    }
}