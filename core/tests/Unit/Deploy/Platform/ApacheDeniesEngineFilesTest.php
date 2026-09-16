<?php

namespace Tests\Unit\Deploy\Platform;

use PHPUnit\Framework\TestCase;

/**
 * ProjeQtOr is flat PHP served from the repository root, so the engine's own
 * generated files sit in the document root beside index.php. Fetched over the
 * public URL, `/docker-compose.yml` returned 200 with its real contents --
 * the base image, the account uid, the published ports and the name of every
 * environment variable the project was given -- and `/panelalpha-entrypoint.sh`
 * likewise. The dotfile rules beside them were doing their job on `.git` and
 * `.env`; nothing covered these two.
 *
 * nginx-site.stub has denied them since it was written. This is the same rule
 * for the Apache path.
 */
class ApacheDeniesEngineFilesTest extends TestCase
{
    private function stub(): string
    {
        $path = __DIR__ . '/../../../../resources/deploy/templates/apache-vhost.stub';
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'apache-vhost.stub is unreadable');

        return $contents;
    }

    public function test_the_generated_compose_file_is_denied(): void
    {
        $this->assertMatchesRegularExpression(
            '/FilesMatch\s+"\^\(\?:docker-compose\\\\\.ya\?ml\|panelalpha\[-\.\]\)"/',
            $this->stub(),
            'the Apache vhost serves the engine\'s own generated files'
        );
    }

    /** The deny has to actually deny, not merely match. */
    public function test_the_match_carries_a_denial(): void
    {
        $stub = $this->stub();
        $offset = strpos($stub, 'docker-compose');
        $this->assertIsInt($offset);

        $block = substr($stub, $offset, 200);
        $this->assertStringContainsString('Require all denied', $block);
    }

    /** The dotfile rules that were already working must survive. */
    public function test_dotfiles_are_still_denied_and_acme_still_reachable(): void
    {
        $stub = $this->stub();

        $this->assertStringContainsString('<FilesMatch "^\.(?!well-known)">', $stub);
        $this->assertStringContainsString('<DirectoryMatch "/\.(?!well-known)">', $stub);
    }
}
