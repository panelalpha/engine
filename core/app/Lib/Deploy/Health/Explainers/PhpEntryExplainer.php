<?php

namespace App\Lib\Deploy\Health\Explainers;

use App\Lib\Deploy\Health\Explainer;
use App\Lib\Deploy\Health\ProbedResponse;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * Why a PHP application's `/` was refused rather than served.
 *
 * A 403 on the front page is the webserver saying it has no document for this
 * directory, and where the project keeps its entry point says which of three
 * things happened -- each needing a different sentence, and none of them
 * derivable from the status code alone.
 *
 * The application may keep its entry point one directory down, which is the
 * normal shape for every modern PHP framework: Laravel, Symfony and Magento
 * all put `index.php` under `public/` or `pub/`, and a document root left at
 * the project root then has nothing to serve. It may have `index.php` exactly
 * where the server is looking, in which case the file is not the problem and
 * its permissions are. Or it may have no entry point at all -- a library
 * checked out as though it were a site, which is a repository that was never
 * going to serve anything.
 *
 * Distinct from {@see PhpDocrootExplainer} on purpose. That one explains a
 * document root pointing *above* the interpreter's reach, where the source
 * arrives as text; this one explains a document root pointing at a directory
 * with no index in it. Same misconfiguration in two projects out of three,
 * opposite symptoms, and a sentence about source code being disclosed would
 * be wrong here.
 */
final class PhpEntryExplainer implements Explainer
{
    /**
     * Where PHP applications keep their entry point, in the order worth
     * reporting. Root last: a project with both is served from the subdirectory.
     *
     * @var list<string>
     */
    private const ENTRY_POINTS = [
        'public/index.php',
        'pub/index.php',
        'web/index.php',
        'html/index.php',
        'webroot/index.php',
        'src/index.php',
        'index.php',
    ];

    public function id(): string
    {
        return 'php-entry';
    }

    public function explain(ProjectContext $context, ProbedResponse $response): array
    {
        foreach (self::ENTRY_POINTS as $entry) {
            if (!$context->isFile($entry)) {
                continue;
            }

            if (str_contains($entry, '/')) {
                $dir = dirname($entry);

                return [
                    'detail' => 'The entry point is ' . $entry . ', so the document root should be '
                        . $dir . ' rather than the project root, which has no index to serve.',
                    'fix' => 'Set `docroot: ' . $dir . '` in panelalpha.yaml and redeploy.',
                ];
            }

            // An index-less public/ is what the image serves when nothing is
            // declared, so the root index is not the one being looked for.
            if (is_dir($context->path('public')) && !$context->isFile('public/index.html')) {
                return [
                    'detail' => 'The document root has no index file: public/ exists without one, '
                        . 'while index.php is at the project root.',
                    'fix' => 'Redeploy, which serves the project root when public/ has no index, '
                        . 'or set `docroot` in panelalpha.yaml to the directory holding the entry point.',
                ];
            }

            return [
                'detail' => 'index.php is at the project root, where the server is looking, '
                    . 'so the file is not missing -- it cannot be read.',
                'fix' => 'Check the permissions on ~/project and index.php, then redeploy.',
            ];
        }

        return [
            'detail' => 'There is no index.php anywhere in ~/project, so nothing in this project '
                . 'is a front page.',
            'fix' => 'Add an index.php, or set `docroot` in panelalpha.yaml to the directory that '
                . 'holds the application. A repository that is a library rather than a site has no '
                . 'front page to serve.',
        ];
    }
}
