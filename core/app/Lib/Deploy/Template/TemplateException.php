<?php

namespace App\Lib\Deploy\Template;

use RuntimeException;

class TemplateException extends RuntimeException
{
    public static function missingFile(string $path): self
    {
        return new self("Deploy template not found: {$path}");
    }

    public static function unreadableFile(string $path): self
    {
        return new self("Deploy template could not be read: {$path}");
    }

    public static function unknownPlaceholder(string $name): self
    {
        return new self("Deploy template has no value for '{{ {$name} }}'");
    }

    public static function unsupportedValue(string $name, string $type): self
    {
        return new self("Deploy template value '{$name}' is a {$type}, which cannot be rendered");
    }
}
