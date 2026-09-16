<?php

namespace Tests\Unit\Deploy\Source;

use App\Lib\Deploy\Source\RepoUrl;
use PHPUnit\Framework\TestCase;

/**
 * The one place a repository URL becomes a path, so the spellings that have
 * to collapse to one repository, and the ones that must never become a path
 * at all, are both pinned here.
 */
class RepoUrlTest extends TestCase
{
    /**
     * Four ways of writing the same remote, all of which reach the same
     * recipe directory. An operator pasting the SSH URL and one pasting the
     * browser URL have not chosen different applications.
     */
    public function test_every_spelling_of_one_repository_resolves_alike(): void
    {
        foreach ([
            'https://github.com/matomo-org/matomo',
            'https://github.com/matomo-org/matomo.git',
            'https://github.com/matomo-org/matomo/',
            'git@github.com:matomo-org/matomo.git',
            'https://GitHub.com/Matomo-Org/Matomo',
            'ssh://git@github.com/matomo-org/matomo.git',
        ] as $url) {
            $this->assertSame('github.com/matomo-org/matomo', RepoUrl::slug($url), $url);
        }
    }

    public function test_a_self_hosted_forge_is_no_different(): void
    {
        $this->assertSame(
            'git.example.com/team/app',
            RepoUrl::slug('https://git.example.com/team/app.git')
        );
    }

    public function test_deeper_paths_keep_only_owner_and_repo(): void
    {
        $this->assertSame(
            'gitlab.com/group/project',
            RepoUrl::slug('https://gitlab.com/group/project/-/tree/main')
        );
    }

    public function test_what_names_no_repository_resolves_to_nothing(): void
    {
        foreach (['', 'nonsense', 'https://github.com', 'https://github.com/owner', '/local/path'] as $url) {
            $this->assertNull(RepoUrl::slug($url), $url);
        }
    }

    /**
     * The URL is operator input and the slug becomes a filesystem path, so a
     * segment that could climb out of the tree resolves to nothing rather
     * than to a file somewhere else on the host.
     */
    public function test_traversal_in_a_segment_is_refused(): void
    {
        $this->assertNull(RepoUrl::slug('https://github.com/../../etc'));
        $this->assertNull(RepoUrl::slug('git@github.com:../etc'));
    }

    public function test_credentials_in_the_url_do_not_change_the_repository(): void
    {
        $this->assertSame(
            'github.com/owner/repo',
            RepoUrl::slug('https://token:x-oauth-basic@github.com/owner/repo.git')
        );
    }
}
