<?php

namespace App\Lib\Deploy\DeployLog;

use App\Exceptions\DeployAlreadyRunningException;
use RuntimeException;

/**
 * One deploy at a time per account: an advisory lock on a file in the account's
 * log directory, released by the kernel if the process dies.
 */
final class DeployLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly DeployLogPaths $paths)
    {
    }

    /**
     * @throws DeployAlreadyRunningException when another deploy holds it
     */
    public function acquire(): void
    {
        $handle = @fopen($this->paths->lock(), 'c');
        if ($handle === false) {
            throw new RuntimeException('Could not open deploy lock file.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new DeployAlreadyRunningException('A deployment is already running for this user.');
        }

        $this->handle = $handle;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }
}
