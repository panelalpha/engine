<?php

namespace App\System\Project\Dind\Strategy;

use App\System\Project\Dind as DindProject;
use App\Lib\Deploy\Platform\DeployPlanContext;
use App\Lib\Deploy\Platform\HostScript;
use App\Lib\Deploy\Platform\AppConfig\AppConfig;
use App\Lib\Deploy\Platform\PlatformStage;
use App\Lib\Deploy\Platform\StageResolver;

/**
 * Everything the project's app config does to the checkout, before anything is
 * detected.
 *
 * An app config is a bootstrap, not a strategy: it writes its file snippets, its
 * compose file if it ships one, and runs its after-clone script. What that
 * leaves behind is what detection reads — a compose file an app config wrote is
 * the repository's compose file as far as the next step is concerned, and an
 * app config that writes none leaves the repository's own in place.
 */
class AppConfigBootstrap
{
    private const TIMEOUT_SECONDS = 3600;

    private DindProject $dind;

    public function __construct(DindProject $dind)
    {
        $this->dind = $dind;
    }

    public function run(?AppConfig $appConfig, string $projectDir, ?string $chown): void
    {
        // A deploy request may speak for the prepare stage of a project that
        // has no app config at all, so the scripts run on their own terms rather
        // than as something an app config brought with it.
        if ($appConfig === null) {
            $this->runScripts($projectDir, null);

            return;
        }

        $this->dind->installFileSnippets();
        $this->writeCompose($appConfig, $projectDir, $chown);
        $this->writeEntrypoint($appConfig, $projectDir, $chown);
        $this->runScripts($projectDir, $appConfig);
    }

    /**
     * An entrypoint the app config supplies, written where a repository would
     * have put its own — {@see EntrypointWriter} reads one path, whoever
     * wrote it.
     */
    private function writeEntrypoint(AppConfig $appConfig, string $projectDir, ?string $chown): void
    {
        $script = $appConfig->entrypoint();
        if ($script === null) {
            return;
        }

        $path = rtrim($projectDir, '/') . '/' . EntrypointWriter::PROJECT_OVERRIDE;
        $this->dind->shell()->execAsUser(['mkdir', '-p', dirname($path)]);
        $this->dind->system()->filesystem()->filePutContents($path, $script, $chown, '755');
    }

    /**
     * The compose file the app config ships, written where Compose will find it.
     *
     * `override` goes to the name Compose reserves for layering; `replace`
     * takes over the project's own, stashing anything that would shadow it.
     */
    private function writeCompose(AppConfig $appConfig, string $projectDir, ?string $chown): void
    {
        $content = $appConfig->compose();
        if ($content === null) {
            return;
        }

        $system = $this->dind->system();
        if ($appConfig->composeMode() === AppConfig::COMPOSE_OVERRIDE) {
            $system->filesystem()->filePutContents($this->dind->userAppComposeOverridePath(), $content, $chown, '644');

            return;
        }

        $this->dind->composeWriter()->stashComposeFilesThatShadow($projectDir);
        $system->filesystem()->filePutContents($this->dind->userAppComposeFilePath(), $content, $chown, '644');
    }

    /**
     * The app config's own prepare-stage work: its staged commands, then its
     * after-clone script.
     *
     * No platform is passed, because none has been chosen yet — that is the
     * point of running here. The platform's own prepare commands run later,
     * once detection has said which platform it is.
     */
    private function runScripts(string $projectDir, ?AppConfig $appConfig): void
    {
        $plan = app(DeployPlanContext::class)->get();
        $staged = $appConfig?->commands(PlatformStage::PREPARE) ?? [];
        $extra = [];
        $setup = $appConfig?->setupCommands();
        if (is_string($setup) && $setup !== '') {
            $extra[AppConfig::SETUP_SCRIPT] = $setup;
        }
        if (HostScript::isEmpty(null, PlatformStage::PREPARE, null, $extra, $staged, $plan)) {
            return;
        }

        $logger = $this->dind->shell()->logger();
        $logger?->info(StageResolver::isOverridden(PlatformStage::PREPARE, $plan)
            ? 'Running prepare commands (replaced by the deploy request)'
            : 'Running setup commands (' . AppConfig::SETUP_SCRIPT . ')');
        $this->dind->strategy()->prepare()->execute(
            $projectDir,
            HostScript::render(null, PlatformStage::PREPARE, null, [], $extra, $staged, $plan),
            self::TIMEOUT_SECONDS
        );
        $logger?->ok('Setup commands finished');
    }
}
