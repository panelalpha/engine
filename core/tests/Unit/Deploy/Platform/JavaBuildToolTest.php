<?php

namespace Tests\Unit\Deploy\Platform;

use App\Lib\Deploy\DetectProjectStrategy;
use PHPUnit\Framework\TestCase;

/**
 * A repository carrying both build files must resolve to one build tool for
 * everything: the image, the build command and the start command.
 *
 * It did not. `java-gradle` outranked `java` on priority, so the commands came
 * from Gradle, while JavaRuntime checks pom.xml first, so the image came from
 * Maven. spring-petclinic ships both — it compiled with mvn into `target/` and
 * then restart-looped on `PANELALPHA: no runnable jar in build/libs`.
 */
class JavaBuildToolTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pa-java-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
    }

    public function test_a_project_with_both_build_files_is_maven_throughout(): void
    {
        file_put_contents($this->dir . '/pom.xml', '<project></project>');
        file_put_contents($this->dir . '/build.gradle', 'plugins {}');

        $decision = DetectProjectStrategy::detect($this->dir);

        $this->assertSame('Java (Maven)', $decision['label']);
        $this->assertStringContainsString('maven', $decision['image']);
        $this->assertStringContainsString('mvn ', $decision['build_command']);
        $this->assertStringContainsString('target/', $decision['start_command']);
        $this->assertStringNotContainsString('build/libs', $decision['start_command']);
    }

    public function test_a_gradle_only_project_is_gradle_throughout(): void
    {
        file_put_contents($this->dir . '/build.gradle', 'plugins {}');

        $decision = DetectProjectStrategy::detect($this->dir);

        $this->assertSame('Java (Gradle)', $decision['label']);
        $this->assertStringContainsString('gradle', $decision['image']);
        $this->assertStringContainsString('gradle ', $decision['build_command']);
        $this->assertStringContainsString('build/libs', $decision['start_command']);
    }

    public function test_a_kotlin_gradle_only_project_is_gradle_too(): void
    {
        file_put_contents($this->dir . '/build.gradle.kts', 'plugins {}');

        $decision = DetectProjectStrategy::detect($this->dir);

        $this->assertSame('Java (Gradle)', $decision['label']);
        $this->assertStringContainsString('build/libs', $decision['start_command']);
    }
}
