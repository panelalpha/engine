<?php

namespace App\Console\Commands\Concerns;

/**
 * The hosting account is a project, and the option that names one is
 * `--project`. `--username` (and `--user`, where that was the spelling) still
 * work so nothing scripted against them breaks.
 *
 * Command internals keep reading the old key: folding the new option into it
 * once, up front, keeps the compatibility in one place instead of at every
 * point of use.
 */
trait ResolvesProject
{
    protected function foldProjectOption(string $legacy = 'username'): void
    {
        $project = $this->option('project');
        if (is_string($project) && $project !== '') {
            $this->input->setOption($legacy, $project);
        }
    }
}
