<?php

namespace App\Console\Commands\Git;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait PrintsGitJson
{
    use DispatchesApiRoute;

    protected function configure(): void
    {
        parent::configure();

        if (!$this->getDefinition()->hasOption('raw')) {
            $this->getDefinition()->addOption(
                new InputOption('raw', null, InputOption::VALUE_NONE, 'Print raw JSON (for piping to jq)')
            );
        }
    }

    protected function resolvePath(): ?string
    {
        $path = $this->option('path');
        if (!is_string($path)) {
            return null;
        }
        $path = trim($path);

        return $path === '' ? null : $path;
    }

    /**
     * @param array<string, mixed> $params
     * @throws \JsonException
     */
    protected function dispatchGit(string $method, string $suffix, array $params): int
    {
        $username = (string) $this->argument('username');
        $response = $this->dispatchApiRoute(
            $method,
            '/projects/' . rawurlencode($username) . $suffix,
            $params
        );
        if ($response->getStatusCode() >= 400) {
            $this->error($this->errorMessage($response));

            return self::FAILURE;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return self::SUCCESS;
        }

        if ($this->option('raw')) {
            $this->output->writeln($content, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->line((string)json_encode($decoded, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line($content);

        return self::SUCCESS;
    }
}
