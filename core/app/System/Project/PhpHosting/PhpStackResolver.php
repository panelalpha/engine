<?php

namespace App\System\Project\PhpHosting;

use App\Models\User as ModelsUser;
use App\System;
use App\System\Project\PhpHosting;

final class PhpStackResolver
{
    public static function resolve(System $system, ModelsUser $model, PhpHosting $project): PhpStack
    {
        $webserver = $system->webserver()->getCurrentWebserver();

        return match ($webserver) {
            'litespeed', 'openlitespeed' => new LiteSpeedStack($system, $model),
            'apache', 'nginx' => new FpmStack($system, $model),
            'nginx-proxy' => new FpmApacheStack($system, $model),
            default => throw new \Exception("Invalid webserver: {$webserver}"),
        };
    }
}
