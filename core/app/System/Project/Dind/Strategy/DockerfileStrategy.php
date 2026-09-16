<?php

namespace App\System\Project\Dind\Strategy;

use App\System\Project\Dind as DindProject;
use App\Lib\Deploy\Compose\ComposeHarden;
use App\Lib\Deploy\Detect\DockerfileFinder;
use App\Lib\Deploy\Compose\DeployCompose;

/**
 * A repository that ships its own Dockerfile.
 *
 * The lightest strategy there is, and deliberately so: the author wrote that
 * Dockerfile and their choices stand, so nothing is generated but the compose
 * file that builds it. The one exception is Rails, whose boot contract needs
 * things the Dockerfile cannot supply for itself — a secret_key_base that
 * lives in a gitignored master.key, and TLS settings that would otherwise
 * send HSTS for a self-signed hosting certificate.
 */
class DockerfileStrategy
{
    private const DEFAULT_PORT = 80;

    private DindProject $dind;

    public function __construct(DindProject $dind)
    {
        $this->dind = $dind;
    }

    /**
     * The container paths this project's Dockerfile declares as volumes.
     *
     * Read through the account's own file layer rather than the host's: the
     * checkout belongs to the account and is not readable as www-data.
     *
     * @return list<string>
     */
    private function declaredVolumes(string $projectDir, string $dockerfile): array
    {
        $contents = $this->dind->projectTree()->readIn($projectDir, $dockerfile);

        return $contents === null ? [] : DockerfileFinder::declaredVolumesIn($contents);
    }

    /**
     * @param array{dockerfile: ?string, port_hint: ?int, ...} $decision
     */
    public function apply(array $decision, string $projectDir, ?string $chown): void
    {
        $strategy = $this->dind->strategy();
        $dockerfile = $decision['dockerfile'] ?? 'Dockerfile';
        $port = (int) ($decision['port_hint'] ?? self::DEFAULT_PORT);
        if ($port <= 0) {
            $port = self::DEFAULT_PORT;
        }

        $decision = $strategy->sidecars()->mergeRuntimeSidecars(
            [
                'env' => array_merge(
                    ComposeHarden::urlEnvironment($this->dind->publicAppUrl()),
                    $this->environment($projectDir),
                    $strategy->entrypoint()->deployPhaseEnvironment()
                ),
                // Paths the image declares as volumes. Without naming them,
                // Docker invents an anonymous volume per path -- data the
                // engine cannot see, back up, or keep across a recreate.
                'dockerfile_volumes' => $this->declaredVolumes($projectDir, $dockerfile),
            ],
            $strategy->sidecars()->runtimeSidecarsFromProject($projectDir)
        );
        $this->dind->composeWriter()->writeGeneratedCompose(
            $projectDir,
            DeployCompose::dockerfile(
                $dockerfile,
                $port,
                $strategy->composeDecision($decision)
            ),
            $chown
        );
    }

    /**
     * What a repo's own Dockerfile cannot provide for itself.
     *
     * For a generic app this stays empty: the author wrote that Dockerfile
     * and their choices stand. Rails is the exception.
     *
     * @return array<string, string>
     */
    private function environment(string $projectDir): array
    {
        $ruby = $this->dind->strategy()->ruby();

        return $ruby->app($projectDir)->isRails() ? $ruby->proxyEnvironment($projectDir) : [];
    }
}
