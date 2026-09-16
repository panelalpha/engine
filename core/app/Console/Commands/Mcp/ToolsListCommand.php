<?php

namespace App\Console\Commands\Mcp;

use App\Mcp\Servers\EngineServer;
use App\Mcp\ToolPolicy;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use ReflectionClass;

/**
 * Shows which tools the current configuration actually exposes.
 *
 * Reading four env vars and predicting the result is exactly the kind of thing
 * that goes wrong quietly, so this prints the answer the server would give.
 */
class ToolsListCommand extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['mcp:tools'];

    protected $signature = 'mcp:tool:list
                            {--toolset= : Only show tools in this group}
                            {--excluded : Show what the configuration removes instead}';

    protected $description = 'List the MCP tools this configuration exposes';

    public function handle(): int
    {
        $policy = new ToolPolicy();

        $all = $this->allTools();
        $exposed = $policy->filter($all);
        $excluded = array_values(array_diff($all, $exposed));

        $this->line('');
        $this->line(sprintf(
            '  <fg=gray>toolsets</> %s   <fg=gray>mode</> %s   <fg=gray>denied</> %s',
            config('mcp-tools.toolsets') ?: 'all',
            $policy->mode(),
            config('mcp-tools.denied') ?: (config('mcp-tools.denied_regex') ? 'regex' : 'none')
        ));

        $shown = $this->option('excluded') ? $excluded : $exposed;
        $filter = $this->option('toolset');

        $rows = [];
        foreach ($shown as $class) {
            $toolset = $policy->toolsetOf($class);

            if ($filter !== null && strtolower($filter) !== $toolset) {
                continue;
            }

            $rows[] = [
                $policy->nameOf($class),
                $toolset,
                $policy->verbOf($class),
                // Not derived from the verb: a POST that only reads is a read,
                // and the Access column is what a reader checks before setting
                // MCP_PERMISSION_MODE.
                $policy->accessOf($class),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $this->line('');
        $this->table(['Tool', 'Toolset', 'Verb', 'Access'], $rows);

        $this->line(sprintf(
            '  <fg=green>%d exposed</>, <fg=yellow>%d withheld</>, %d total.',
            count($exposed),
            count($excluded),
            count($all)
        ));
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * The unfiltered set: what the server would register with no config at all.
     *
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    private function allTools(): array
    {
        $defaults = (new ReflectionClass(EngineServer::class))
            ->getProperty('tools')
            ->getDefaultValue();

        $generated = app_path('Mcp/Tools/Api/generated-tools.php');

        return array_merge($defaults, is_file($generated) ? require $generated : []);
    }
}
