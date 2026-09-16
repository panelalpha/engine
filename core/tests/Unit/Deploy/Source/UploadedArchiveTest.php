<?php

namespace Tests\Unit\Deploy\Source;

use App\Lib\Deploy\Source\UploadedArchive;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What an account is allowed to hand the extractor.
 *
 * Every rule here runs before a single byte is unpacked, and each one closes
 * a way of reaching a file the account does not own: a relative path climbing
 * out with `..`, a symlink pointing somewhere else, or an archive whose
 * extension does not match anything we know how to open.
 */
class UploadedArchiveTest extends TestCase
{
    private string $home = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->home = sys_get_temp_dir() . '/pa-home-' . bin2hex(random_bytes(8));
        mkdir($this->home, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->home . '/*') as $entry) {
            is_dir((string) $entry) && !is_link((string) $entry)
                ? rmdir((string) $entry)
                : unlink((string) $entry);
        }
        if (is_dir($this->home)) {
            rmdir($this->home);
        }
        parent::tearDown();
    }

    private function touchInHome(string $name): string
    {
        $path = $this->home . '/' . $name;
        file_put_contents($path, 'PK');

        return $path;
    }

    public function test_an_absolute_path_inside_the_home_is_accepted(): void
    {
        $path = $this->touchInHome('site.zip');

        $archive = UploadedArchive::inHome($path, $this->home);

        $this->assertSame(realpath($path), $archive->path);
        $this->assertTrue($archive->isZip);
    }

    public function test_a_relative_path_is_resolved_against_the_home(): void
    {
        $this->touchInHome('site.zip');

        $archive = UploadedArchive::inHome('site.zip', $this->home);

        $this->assertSame(realpath($this->home . '/site.zip'), $archive->path);
    }

    public function test_a_trailing_slash_on_the_home_changes_nothing(): void
    {
        $this->touchInHome('site.zip');

        $archive = UploadedArchive::inHome('site.zip', $this->home . '/');

        $this->assertSame(realpath($this->home . '/site.zip'), $archive->path);
    }

    public function test_a_missing_archive_is_named_in_the_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Archive not found');

        UploadedArchive::inHome('nothing-here.zip', $this->home);
    }

    /**
     * The check is on where the path lands, not on how it is spelled — a
     * symlink is the way a spelling-based check gets walked around.
     */
    public function test_a_symlink_pointing_out_of_the_home_is_refused(): void
    {
        $outside = sys_get_temp_dir() . '/pa-outside-' . bin2hex(random_bytes(8)) . '.zip';
        file_put_contents($outside, 'PK');
        symlink($outside, $this->home . '/escape.zip');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('outside the user home directory');

            UploadedArchive::inHome('escape.zip', $this->home);
        } finally {
            @unlink($outside);
        }
    }

    public function test_an_extension_we_cannot_open_is_refused(): void
    {
        $this->touchInHome('payload.php');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported archive format');

        UploadedArchive::inHome('payload.php', $this->home);
    }

    public function test_the_format_is_read_case_insensitively(): void
    {
        $this->touchInHome('SITE.ZIP');

        $this->assertTrue(UploadedArchive::inHome('SITE.ZIP', $this->home)->isZip);
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function formats(): array
    {
        return [
            ['site.zip', true],
            ['site.tar.gz', false],
            ['site.tgz', false],
        ];
    }

    #[DataProvider('formats')]
    public function test_each_supported_format_stages_under_its_own_name(string $name, bool $isZip): void
    {
        $this->touchInHome($name);

        $archive = UploadedArchive::inHome($name, $this->home);

        $this->assertSame($isZip, $archive->isZip);
        $this->assertSame($isZip ? 'archive.zip' : 'archive.tar.gz', $archive->stagedName());
    }

    public function test_a_zip_is_listed_and_extracted_with_the_zip_tools(): void
    {
        $this->touchInHome('site.zip');
        $archive = UploadedArchive::inHome('site.zip', $this->home);

        $this->assertSame(['sudo', 'unzip', '-Z1', '/staged'], $archive->listNamesArgv('/staged'));
        $this->assertSame(['sudo', 'unzip', '-Z', '/staged'], $archive->listModesArgv('/staged'));
        $this->assertSame(
            [
                'sudo', 'setpriv', '--reuid', '1001', '--regid', '1001', '--clear-groups',
                'unzip', '-UU', '-o', '/staged', '-d', '/tmp/x',
            ],
            $archive->extractArgv(1001, 1001, '/staged', '/tmp/x')
        );
    }

    public function test_a_tarball_is_listed_and_extracted_with_the_tar_tools(): void
    {
        $this->touchInHome('site.tar.gz');
        $archive = UploadedArchive::inHome('site.tar.gz', $this->home);

        $this->assertSame(['sudo', 'tar', '-tzf', '/staged'], $archive->listNamesArgv('/staged'));
        $this->assertSame(['sudo', 'tar', '-tvzf', '/staged'], $archive->listModesArgv('/staged'));
        $this->assertSame(
            [
                'sudo', 'setpriv', '--reuid', '1001', '--regid', '1001', '--clear-groups', 'tar',
                '--no-same-owner', '--no-same-permissions', '-xzf', '/staged', '-C', '/tmp/x',
            ],
            $archive->extractArgv(1001, 1001, '/staged', '/tmp/x')
        );
    }

    /**
     * An upload does not get to choose who owns what it unpacks to: a tar can
     * carry uid/gid and mode, and restoring them would let an archive drop a
     * setuid binary owned by root into the account.
     */
    public function test_extraction_never_restores_an_owner_or_a_mode(): void
    {
        $this->touchInHome('site.tar.gz');
        $argv = UploadedArchive::inHome('site.tar.gz', $this->home)
            ->extractArgv(1001, 1001, '/staged', '/tmp/x');

        $this->assertContains('--no-same-owner', $argv);
        $this->assertContains('--no-same-permissions', $argv);
        $this->assertSame(
            ['sudo', 'setpriv', '--reuid', '1001', '--regid', '1001', '--clear-groups'],
            array_slice($argv, 0, 7),
            'extraction runs as the account, by id: the core container has no passwd entry for it'
        );
    }
}
