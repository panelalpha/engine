<?php

namespace App\System\Project;

use App\System\Project as UserProject;

class Php
{
    public function __construct(
        private readonly UserProject $project,
    ) {
    }

    public function customIniFilePath(string $phpVersion): string
    {
        return $this->project->projectDirPath() . "/php/{$phpVersion}/custom.ini";
    }

    /**
     * @return array<string, string>
     */
    public function getCustomIniSettings(string $phpVersion): array
    {
        $iniFile = $this->customIniFilePath($phpVersion);

        return parse_ini_file($iniFile);
    }

    /**
     * @param array<string, string> $settings
     *
     * @throws \ErrorException
     */
    public function updateCustomIniSettings(string $phpVersion, array $settings): void
    {
        $iniFile = $this->customIniFilePath($phpVersion);

        $iniString = '';
        foreach ($settings as $key => $value) {
            $iniString .= $key . '=' . $value . "\n";
        }

        // use parse_ini_string() to validate the string
        parse_ini_string($iniString);

        file_put_contents($iniFile, $iniString);
        $this->restartPhpHandler($phpVersion);
    }

    private function restartPhpHandler(string $phpVersion): void
    {
        $runtime = $this->project->runtime();
        if ($runtime instanceof PhpHosting) {
            $runtime->phpRuntime()->restartPhpHandler($phpVersion);
        }
    }
}
