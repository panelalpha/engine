<?php

namespace App\Lib\Domains;

/**
 * A domain the caller named cannot be had.
 *
 * Only thrown for a name the caller asked for by hand -- a
 * `*.panelalpha.online` label someone else already holds. A *generated* name
 * never throws: the ladder drops a rung and says why in `fallback_reason`,
 * because "you did not name a domain and the preferred zone was busy" is not
 * a reason to refuse to create a project.
 */
class DomainAllocationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'domain_unavailable')
    {
        parent::__construct($message);
    }
}
