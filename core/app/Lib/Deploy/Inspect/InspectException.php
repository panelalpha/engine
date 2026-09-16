<?php

namespace App\Lib\Deploy\Inspect;

/**
 * A source that could not be opened: an unreachable repository, a path that is
 * not a directory, a project with no files yet.
 *
 * Separate from \InvalidArgumentException on purpose — the controller answers
 * this with 422 (the caller asked for something that cannot be read) rather
 * than with a 500 (the engine broke).
 */
class InspectException extends \RuntimeException
{
}
