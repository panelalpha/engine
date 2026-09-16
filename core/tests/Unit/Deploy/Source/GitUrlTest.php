<?php

namespace Tests\Unit\Deploy\Source;

use App\Lib\Deploy\Source\GitUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class GitUrlTest extends TestCase
{
    public function test_askpass_supplies_token_without_putting_it_in_command_arguments(): void
    {
        $token = "s3cret token'\nsecond-line";
        $path = tempnam(sys_get_temp_dir(), 'git-askpass-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, GitUrl::askPassScript($token));
        chmod($path, 0700);

        try {
            $command = GitUrl::withAskPass(
                ['git', 'clone', 'https://github.com/org/repo.git', '/srv/project'],
                $path
            );
            $this->assertStringNotContainsString($token, implode(' ', $command));
            $this->assertContains('https://github.com/org/repo.git', $command);

            $process = new Process([$path, 'Password for https://git@github.com:']);
            $process->mustRun();
            $this->assertSame($token, $process->getOutput());
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_empty_askpass_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GitUrl::askPassScript('  ');
    }

    public function test_sanitize_strips_credentials(): void
    {
        $this->assertSame(
            'https://github.com/org/repo.git',
            GitUrl::sanitize('https://git:s3cret%20token@github.com/org/repo.git')
        );
    }

    public function test_sanitize_leaves_clean_url(): void
    {
        $this->assertSame(
            'https://github.com/org/repo.git',
            GitUrl::sanitize('https://github.com/org/repo.git')
        );
    }

    #[DataProvider('unsafeTokenUrlProvider')]
    public function test_rejects_token_for_unsafe_repository_url(string $url): void
    {
        $this->assertFalse(GitUrl::isHttpsWithoutCredentials($url));

        $this->expectException(\InvalidArgumentException::class);
        GitUrl::assertSafeForToken($url);
    }

    public static function unsafeTokenUrlProvider(): array
    {
        return [
            'plaintext HTTP' => ['http://git.example.com/org/repo.git'],
            'embedded username' => ['https://user@git.example.com/org/repo.git'],
            'embedded password' => ['https://user:password@git.example.com/org/repo.git'],
            'non-HTTP scheme' => ['ftp://git.example.com/org/repo.git'],
        ];
    }

    public function test_accepts_clean_https_repository_for_token(): void
    {
        $this->assertTrue(
            GitUrl::isHttpsWithoutCredentials('https://git.example.com/org/repo.git')
        );
    }
}
