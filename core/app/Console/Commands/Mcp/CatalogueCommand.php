<?php

namespace App\Console\Commands\Mcp;

use App\Mcp\Tools\Api\ApiTool;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;
use ReflectionMethod;

/**
 * Renders the MCP tool surface as a single self-contained HTML page.
 *
 * It exists as a command rather than a hand-maintained file because the tool
 * surface moves: a rename or a new endpoint would otherwise leave the page
 * quietly describing a server that no longer exists.
 */
class CatalogueCommand extends Command
{
    protected $signature = 'mcp:catalogue
                            {--out=../docs/mcp-catalogue.html : File to write, relative to core/}
                            {--stdout : Write to standard output instead of a file}';

    protected $description = 'Render the MCP tool catalogue as a self-contained HTML page';

    /**
     * Display order and heading for each toolset. A group missing from here
     * still renders -- it lands at the end under its raw namespace segment --
     * so adding an OpenAPI tag cannot silently drop its tools from the page.
     */
    private const GROUPS = [
        'Engine' => 'Engine summaries',
        'Projects' => 'Projects',
        'Domains' => 'Domains',
        'DomainPHP' => 'Domain PHP',
        'DomainACME' => 'Domain ACME',
        'DomainLogFiles' => 'Domain log files',
        'SSLCertificates' => 'SSL certificates',
        'MySQLDatabases' => 'MySQL databases',
        'MySQLUsers' => 'MySQL users',
        'MySQLPrivileges' => 'MySQL privileges',
        'MySQLServer' => 'MySQL server',
        'FTPAccounts' => 'FTP accounts',
        'SFTPAccounts' => 'SFTP accounts',
        'CronJobs' => 'Cron jobs',
        'Files' => 'Files',
        'PHP' => 'PHP',
        'Containers' => 'Containers',
        'AppUsers' => 'App users',
        'Usage' => 'Usage',
        'WPCLI' => 'WP-CLI',
        'Deploy' => 'Deploy',
        'ProxyRules' => 'Proxy rules',
        'System' => 'System',
        'ServerMetrics' => 'Server metrics',
        'CSF' => 'CSF firewall',
        'IPManagement' => 'IP management',
        'ModSecurity' => 'ModSecurity',
        'Lighthouse' => 'Lighthouse',
    ];

    public function handle(): int
    {
        $stub = __DIR__ . '/stubs/catalogue.html.stub';

        if (!is_file($stub)) {
            $this->error("Template not found at {$stub}.");

            return self::FAILURE;
        }

        // The whole catalogue, not just what config currently exposes -- this
        // documents the server's surface, and a page that changed shape with
        // MCP_TOOLSETS would be a poor reference.
        $classes = array_merge(
            [\App\Mcp\Tools\MetricsLatestTool::class, \App\Mcp\Tools\ProjectListSummaryTool::class],
            require base_path('app/Mcp/Tools/Api/generated-tools.php')
        );

        $grouped = [];
        $readOnly = 0;

        foreach ($classes as $class) {
            $tool = $this->describe(new $class());
            $grouped[$tool['group']][] = $tool;

            if ($tool['risk'] === 'readonly') {
                $readOnly++;
            }
        }

        $total = count($classes);

        $html = strtr(file_get_contents($stub), [
            '{{TOOLS}}' => $this->renderGroups($grouped),
            '{{TOTAL}}' => (string)$total,
            '{{READONLY}}' => (string)$readOnly,
            '{{DESTRUCTIVE}}' => (string)($total - $readOnly),
            '{{GROUPS}}' => (string)count($grouped),
            '{{PACKAGE}}' => $this->packageVersion(),
            '{{PROTOCOL}}' => $this->protocolVersions(),
        ]);

        if ($this->option('stdout')) {
            // Straight to the stream, not through $this->output: the console
            // output object walks everything it writes looking for style tags,
            // which on a 100KB page of <div>s costs the better part of a minute.
            fwrite(STDOUT, $html);

            return self::SUCCESS;
        }

        $out = $this->outputPath();

        if (!is_dir(dirname($out))) {
            $this->error('Directory ' . dirname($out) . ' does not exist.');

            return self::FAILURE;
        }

        file_put_contents($out, $html);

        $this->info(sprintf(
            'Wrote %s -- %d tools in %d groups, %d read-only, %d destructive.',
            realpath($out) ?: $out,
            $total,
            count($grouped),
            $readOnly,
            $total - $readOnly
        ));

        return self::SUCCESS;
    }

    /**
     * Absolute paths are taken as given; anything else is relative to core/,
     * which is what the default (../docs/mcp-catalogue.html) relies on.
     */
    private function outputPath(): string
    {
        $out = (string)$this->option('out');

        return str_starts_with($out, '/') ? $out : base_path($out);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Tool $tool): array
    {
        $definition = $tool->toArray();

        $relative = Str::after($tool::class, 'App\\Mcp\\Tools\\');
        $segments = explode('\\', $relative);
        $group = count($segments) >= 3 ? $segments[count($segments) - 2] : 'Engine';

        $verb = '—';
        $path = 'built in';

        if ($tool instanceof ApiTool) {
            $verb = strtoupper($tool->httpMethod());
            $method = new ReflectionMethod($tool, 'path');
            $method->setAccessible(true);
            $path = '/api' . $method->invoke($tool);
        }

        $required = (array)($definition['inputSchema']['required'] ?? []);
        $params = [];

        foreach ((array)($definition['inputSchema']['properties'] ?? []) as $name => $property) {
            $property = (array)$property;
            $params[] = [
                'name' => (string)$name,
                'type' => (string)($property['type'] ?? 'string'),
                'required' => in_array($name, $required, true),
                'note' => (string)($property['description'] ?? ''),
            ];
        }

        return [
            'name' => (string)$definition['name'],
            // The generated descriptions end with "Calls GET /api/...", which
            // the path is already showing right underneath.
            'desc' => trim((string)preg_replace('/\n\nCalls .*$/s', '', (string)($definition['description'] ?? ''))),
            'verb' => $verb,
            'path' => $path,
            'risk' => ($definition['annotations']['readOnlyHint'] ?? false) ? 'readonly' : 'destructive',
            'params' => $params,
            'group' => $group,
        ];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $grouped
     */
    private function renderGroups(array $grouped): string
    {
        $order = array_merge(
            array_intersect(array_keys(self::GROUPS), array_keys($grouped)),
            array_diff(array_keys($grouped), array_keys(self::GROUPS))
        );

        $html = '<div id="list">';

        foreach ($order as $group) {
            $tools = $grouped[$group];
            usort($tools, fn (array $a, array $b): int => $a['name'] <=> $b['name']);

            $readOnly = count(array_filter($tools, fn (array $t): bool => $t['risk'] === 'readonly'));
            $destructive = count($tools) - $readOnly;

            $html .= '<section class="group" data-group="' . $this->e($group) . '">' . "\n"
                . '<button class="group-head" type="button" aria-expanded="true">' . "\n"
                . '<span class="chev" aria-hidden="true"></span>' . "\n"
                . '<h3>' . $this->e(self::GROUPS[$group] ?? $group) . '</h3>' . "\n"
                . '<span class="counts"><b>' . count($tools) . '</b>'
                . '<span class="dot ro" title="' . $readOnly . ' read-only"></span>' . $readOnly
                . '<span class="dot de" title="' . $destructive . ' destructive"></span>' . $destructive
                . '</span>' . "\n</button>\n<div class=\"tools\">";

            foreach ($tools as $tool) {
                $html .= $this->renderTool($tool);
            }

            $html .= "</div>\n</section>";
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function renderTool(array $tool): string
    {
        $search = strtolower($tool['name'] . ' ' . $tool['desc'] . ' ' . $tool['path']);

        $html = '<article class="tool ' . $tool['risk'] . '" data-name="' . $this->e($tool['name'])
            . '" data-risk="' . $tool['risk'] . '" data-search="' . $this->e($search) . '">' . "\n"
            . '<header class="tool-head">' . "\n"
            . '<code class="tool-name">' . $this->e($tool['name']) . '</code>' . "\n"
            . '<span class="risk-chip ' . $tool['risk'] . '">'
            . ($tool['risk'] === 'readonly' ? 'read-only' : 'destructive') . '</span>' . "\n"
            . '</header>' . "\n"
            . '<p class="tool-desc">' . $this->e($tool['desc']) . '</p>' . "\n"
            . '<div class="tool-meta"><span class="verb v' . $this->e($tool['verb']) . '">'
            . $this->e($tool['verb']) . '</span><code class="path">' . $this->e($tool['path'])
            . '</code></div>' . "\n<div class=\"params\">";

        if ($tool['params'] === []) {
            $html .= '<span class="noparams">no parameters</span>';
        }

        foreach ($tool['params'] as $param) {
            $title = $param['note'] !== '' ? ' title="' . $this->e($param['note']) . '"' : '';
            $html .= '<span class="pill' . ($param['required'] ? ' req' : '') . '"' . $title . '>'
                . $this->e($param['name']) . '<i>' . $this->e($param['type']) . '</i></span>';
        }

        return $html . "</div>\n</article>";
    }

    private function packageVersion(): string
    {
        $lock = base_path('composer.lock');

        if (is_file($lock)) {
            $data = json_decode((string)file_get_contents($lock), true);

            foreach ((array)($data['packages'] ?? []) as $package) {
                if (($package['name'] ?? '') === 'laravel/mcp') {
                    return (string)$package['version'];
                }
            }
        }

        return 'unknown';
    }

    private function protocolVersions(): string
    {
        if (!class_exists(\Laravel\Mcp\Enums\ProtocolVersion::class)) {
            return 'unknown';
        }

        $supported = \Laravel\Mcp\Enums\ProtocolVersion::supported();
        $values = array_map(
            fn ($v): string => is_object($v) && property_exists($v, 'value') ? (string)$v->value : (string)$v,
            is_array($supported) ? $supported : [$supported]
        );
        sort($values);

        return $values === [] ? 'unknown' : reset($values) . ' → ' . end($values);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
