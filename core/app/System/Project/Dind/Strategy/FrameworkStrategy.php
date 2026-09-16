<?php

namespace App\System\Project\Dind\Strategy;

use App\System\Project\Dind as DindProject;
use App\Lib\Deploy\Compose\DeployCompose;
use App\Lib\Deploy\Platform\Dockerfile\DockerIgnore;
use App\Lib\Deploy\Platform\Dockerfile\NginxConfig;
use App\Lib\Deploy\Platform\DockerfileBuilder;
use App\Lib\Deploy\Platform\AppConfig\AppConfig;
use App\Lib\Deploy\Platform\StageScript;
use App\Lib\Deploy\Platform\PlatformManifest;
use App\Lib\Deploy\Platform\ProjectContext;
use App\Lib\Deploy\Platform\Runtime\HostNodeBuild;
use App\Lib\Deploy\Platform\Runtime\HostRunProject;
use App\Lib\Deploy\Platform\Runtime\Images;
use App\Lib\Deploy\Platform\Runtime\JsPackageManager;
use App\Lib\Deploy\Platform\Runtime\StandaloneNodeServe;

/**
 * A framework a manifest recognised: write the Dockerfile for it, plus a
 * compose file that builds it.
 *
 * Two shapes, and the split is about where the build happens. A recipe that
 * compiles to static files or to a standalone Node server is built on the
 * *host* (see {@see \App\System\Project\Dind\HostCompile}) and
 * then served from a stock image, so there is no build here at all. Everything
 * else gets a generated Dockerfile.
 *
 * The generated name is `panelalpha.Dockerfile` rather than `Dockerfile` so
 * that the next detect pass still sees the framework it is, instead of
 * mistaking the engine's own output for a Dockerfile the user wrote.
 */
class FrameworkStrategy
{
    private const NGINX_PORT = 8080;

    private const NODE_PORT = 3000;

    private DindProject $dind;

    public function __construct(DindProject $dind)
    {
        $this->dind = $dind;
    }

    /**
     * @param array<string, mixed> $decision
     */
    public function apply(
        array $decision,
        string $projectDir,
        ?string $chown,
        ?AppConfig $appConfig = null
    ): void {
        $isNginx = ($decision['runtime'] ?? '') === PlatformManifest::RUNTIME_NGINX;
        // Both compose-writing paths below read $decision['env'], so the phase
        // goes in once here rather than at each of them.
        $decision['env'] = array_merge(
            is_array($decision['env'] ?? null) ? $decision['env'] : [],
            $this->dind->strategy()->entrypoint()->deployPhaseEnvironment()
        );

        if ($isNginx || DeployCompose::isHostCompiled($decision)) {
            $this->applyHostCompiled($decision, $projectDir, $chown, $isNginx, $appConfig);

            return;
        }

        $this->applyGeneratedBuild($decision, $projectDir, $chown, $appConfig);
    }

    /**
     * nginx-static and standalone-Node recipes are compiled on the host and
     * served from a stock image — no build here.
     *
     * @param array<string, mixed> $decision
     */
    private function applyHostCompiled(
        array $decision,
        string $projectDir,
        ?string $chown,
        bool $isNginx,
        ?AppConfig $appConfig = null
    ): void {
        $system = $this->dind->system();

        if ($isNginx) {
            $system->filesystem()->filePutContents(
                $projectDir . '/' . NginxConfig::FILENAME,
                NginxConfig::contents(),
                $chown,
                '644'
            );
        }
        $port = (int) ($decision['port_hint'] ?? ($isNginx ? self::NGINX_PORT : self::NODE_PORT));

        // Only a Node project's image is chosen from its lockfile. A command
        // runtime keeps the image its own recipe resolved -- python:3.12-slim,
        // golang, the JDK -- which is also what the host compile used.
        if ($isNginx || HostRunProject::isNode($decision['strategy'] ?? null)) {
            $files = ProjectContext::listRootFiles($projectDir);
            $package = ProjectContext::readPackageJson($projectDir) ?? [];
            // Run under the interpreter the lockfile named, the same one the
            // host compile used. A tree installed by one and imported by
            // another fails on packages only the first can resolve.
            $decision['image'] = HostNodeBuild::runtimeImage(
                JsPackageManager::detectPackageManager($files, $package),
                Images::nodeImage($projectDir, $package)
            );
        } else {
            // A command runtime keeps the version its recipe resolved, but a
            // Python one may still swap the stock image for the shared base
            // carrying the headers a source build needs. A no-op for Go, Rust
            // and Java, whose images are not python tags.
            $decision['image'] = PythonBase::imageFor(
                $this->dind,
                $projectDir,
                (string) ($decision['image'] ?? '')
            );
        }
        if (DeployCompose::isStandaloneNodeOutput($decision)) {
            // Only a bundled server needs the probe script; a mounted project
            // is started by its own `package.json` script.
            $system->filesystem()->filePutContents(
                $projectDir . '/' . StandaloneNodeServe::FILENAME,
                StandaloneNodeServe::script(),
                $chown,
                '644'
            );
        }

        // The staged entrypoint, which this path used to skip entirely.
        //
        // A generated build bakes the script into the image
        // ({@see EntrypointInstall}) and PHP's shared base carries a shim
        // that runs it, so both of those get the platform's install and
        // upgrade commands for free. A host-compiled project runs a stock
        // image -- python:3.12-slim, the JDK, a node tag -- with the account
        // bind-mounted and nothing baked in, so the compose command was the
        // recipe's `start` and that was the whole of it. Django's `migrate`
        // and `collectstatic` are declared in its recipe and simply never
        // ran: the deploy went green, the health probe followed the redirect
        // off the homepage, and the first real page was a 500 on a database
        // with no tables.
        //
        // Writing the script here and running it as the command puts this
        // path back on the same footing as the other two.
        if ($this->dind->strategy()->entrypoint()->write($decision, $projectDir, $chown, [], $appConfig)) {
            $decision['entrypoint'] = StageScript::FILENAME;
        }

        $this->writeCompose($decision, $projectDir, $chown, $port);
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function applyGeneratedBuild(
        array $decision,
        string $projectDir,
        ?string $chown,
        ?AppConfig $appConfig
    ): void {
        $system = $this->dind->system();
        $files = ProjectContext::listRootFiles($projectDir);

        $this->dind->strategy()->entrypoint()->write($decision, $projectDir, $chown, [], $appConfig);
        $system->filesystem()->filePutContents(
            $projectDir . '/' . DockerfileBuilder::FILENAME,
            DockerfileBuilder::generate($decision, $files, $projectDir),
            $chown,
            '644'
        );
        $this->ensureDockerignore($projectDir, $chown);

        $this->writeCompose($decision, $projectDir, $chown, (int) ($decision['port_hint'] ?? self::NODE_PORT));
    }

    /**
     * Give the build a `.dockerignore` when the project has none, and never
     * touch one it wrote itself.
     *
     * The engine used to edit the customer's file, stripping any rule that
     * excluded `.git` so content pipelines could read history during the
     * build. Nothing needs that now — every recipe whose build reads git runs
     * on the host against the real checkout — and the edit was what made the
     * build cache unhittable. {@see DockerIgnore}.
     */
    private function ensureDockerignore(string $projectDir, ?string $chown): void
    {
        $system = $this->dind->system();
        $dockerignore = $projectDir . '/.dockerignore';

        if (!$system->filesystem()->fileExists($dockerignore)) {
            $system->filesystem()->filePutContents($dockerignore, DockerIgnore::contents(), $chown, '644');
        }
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function writeCompose(array $decision, string $projectDir, ?string $chown, int $port): void
    {
        $strategy = $this->dind->strategy();
        $decision = $strategy->sidecars()->mergeRuntimeSidecars(
            $decision,
            $strategy->sidecars()->runtimeSidecarsFromProject($projectDir)
        );
        $this->dind->composeWriter()->writeGeneratedCompose(
            $projectDir,
            DeployCompose::framework(
                $strategy->composeDecision($decision),
                $port,
                $this->dind->publicAppUrl()
            ),
            $chown
        );
    }
}
