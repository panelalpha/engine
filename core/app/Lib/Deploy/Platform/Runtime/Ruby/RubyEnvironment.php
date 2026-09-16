<?php

namespace App\Lib\Deploy\Platform\Runtime\Ruby;

/**
 * The environment a Ruby container runs with.
 *
 * RAILS_* mean nothing to Sinatra or Rack, which read RACK_ENV instead —
 * and an app left at the default RACK_ENV runs in development mode. Both the
 * image and the compose service ask here, so the two cannot drift.
 */
final class RubyEnvironment
{
    /** @var array<string, string> */
    private const RAILS = [
        'RAILS_ENV' => 'production',
        'RAILS_LOG_TO_STDOUT' => '1',
        'RAILS_SERVE_STATIC_FILES' => '1',
    ];

    /** @var array<string, string> */
    private const RACK = ['RACK_ENV' => 'production'];

    /**
     * @return array<string, string>
     */
    public static function for(RubyApp $app): array
    {
        return $app->isRails() ? self::RAILS : self::RACK;
    }
}
