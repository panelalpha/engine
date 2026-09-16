<?php

namespace App\Console\Commands\Deploy;

use App\Lib\Deploy\DeployLog\DeployLogger;
use Illuminate\Console\Command;

class DeployLogsShowCommand extends Command
{
    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['deploy:log:show', 'deploy-logs:show'];

    protected $signature = 'project:deploy:log
                            {project : Project username}
                            {--id= : Deploy id (defaults to latest.json)}
                            {--tail=80 : Number of lines from the end}
                            {--raw : Print raw JSONL instead of formatted lines}';

    protected $description = 'Show deploy log lines for a project (latest deploy, or --id)';

    public function handle(): int
    {
        $username = (string)$this->argument('project');
        $deployId = $this->option('id');
        $tail = max(1, (int)$this->option('tail'));
        $raw = (bool)$this->option('raw');

        try {
            if ($deployId !== null && $deployId !== '') {
                $logger = DeployLogger::forDeploy($username, (string)$deployId);
            } else {
                $logger = DeployLogger::current($username);
                if ($logger === null) {
                    $this->error("No deploy log found for '{$username}' (missing latest.json)");
                    return 1;
                }
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $path = $logger->getLogPath();
        if (!is_file($path)) {
            $this->error("Log file not found: {$path}");
            return 1;
        }

        $latestMeta = DeployLogger::readLatestFor($username);

        $this->line("User:    {$username}");
        $this->line('Deploy:  ' . $logger->getDeployId());
        $this->line('Path:    ' . $path);
        if ($latestMeta !== null && ($latestMeta['id'] ?? null) === $logger->getDeployId()) {
            $this->line('Status:  ' . ($latestMeta['status'] ?? '-'));
            $this->line('Stage:   ' . ($latestMeta['stage'] ?? '-'));
            $this->line('PID:     ' . (($latestMeta['pid'] ?? null) !== null ? (string)$latestMeta['pid'] : '-'));
            if (!empty($latestMeta['error'])) {
                $this->line('Error:   ' . $latestMeta['error']);
            }
        } elseif ($latestMeta !== null) {
            $this->line('Note:    latest.json points to ' . ($latestMeta['id'] ?? '?'));
        }
        $this->line('');

        $all = file($path, FILE_IGNORE_NEW_LINES);
        if ($all === false) {
            $this->error("Could not read {$path}");
            return 1;
        }
        $slice = array_slice($all, -$tail);

        if ($raw) {
            foreach ($slice as $line) {
                $this->line($line);
            }
            return 0;
        }

        foreach ($slice as $line) {
            /** @var mixed $decoded */
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                $this->line($line);
                continue;
            }
            $ts = isset($decoded['ts']) ? date('Y-m-d H:i:s', (int)$decoded['ts']) : '-';
            $level = (string)($decoded['level'] ?? 'dim');
            $msg = (string)($decoded['msg'] ?? '');
            $this->line(sprintf('[%s] %-5s %s', $ts, $level, $msg));
        }

        return 0;
    }
}
