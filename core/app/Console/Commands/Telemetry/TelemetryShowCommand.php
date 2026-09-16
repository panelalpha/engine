<?php

namespace App\Console\Commands\Telemetry;

use App\Lib\Deploy\Telemetry\Telemetry;
use Illuminate\Console\Command;

/**
 * Print a queued report exactly as it would be transmitted.
 *
 * This is the command that answers "what are you actually sending about my
 * customers". It has to show the real bytes, redaction included — a preview
 * that renders anything differently from the wire is worse than none.
 */
class TelemetryShowCommand extends Command
{
    protected $signature = 'telemetry:show
                            {id? : Report id; defaults to the newest queued report}';

    protected $description = 'Print a queued telemetry report as it would be sent';

    public function handle(): int
    {
        // A far-future "now" so backoff never hides a report from an operator
        // who is asking what is in the queue.
        $items = Telemetry::spool()->pending(PHP_INT_MAX, PHP_INT_MAX);
        if ($items === []) {
            $this->info('Nothing queued.');

            return 0;
        }

        $id = $this->argument('id');
        if ($id === null) {
            $item = $items[array_key_last($items)];
        } else {
            $matches = array_values(array_filter($items, static fn (array $i): bool => $i['id'] === $id));
            if ($matches === []) {
                $this->error("No queued report with id {$id}.");

                return 1;
            }
            $item = $matches[0];
        }

        $this->line((string) json_encode($item['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
