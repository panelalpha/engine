<?php

namespace App\System\Project\Git;

class Exception extends \RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 400)
    {
        parent::__construct($message);
    }
}
