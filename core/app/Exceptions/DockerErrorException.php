<?php

namespace App\Exceptions;

use Exception;

class DockerErrorException extends Exception
{
    public function isContainerUnavailable(): bool
    {
        return self::meansContainerUnavailable($this->getMessage());
    }

    /** Docker's "Container x is restarting", compose v2's `service "x" is not running`. */
    public static function meansContainerUnavailable(string $message): bool
    {
        return (bool) preg_match('/\b(container|service)\s+\S+\s+is\s+(restarting|not running)\b/i', $message);
    }
}
