<?php

namespace App\Lib\Deploy\Platform\Dockerfile;

use App\Lib\Deploy\Platform\StageScript;
use App\Lib\Deploy\Template\Template;

/**
 * Installs the staged entrypoint instead of baking a start command in.
 *
 * `CMD ["sh","-c", $start]` runs on every container start, so migrations ran on
 * every restart; the entrypoint branches on the deploy phase. `ENTRYPOINT` and a
 * script ending in `exec` make the server PID 1, so Docker's stop signal reaches it.
 */
final class EntrypointInstall
{
    public static function lines(): string
    {
        return rtrim(
            Template::named('dockerfile/entrypoint-install')->render(['entrypoint' => StageScript::FILENAME]),
            "\n"
        );
    }
}
