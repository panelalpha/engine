<?php

namespace App\Lib\Deploy\Platform\Runtime;

use App\Lib\Deploy\Platform\ProjectContext;

/**
 * Java, where the build tool picks the image: a Gradle project built in the
 * Maven image has no gradle on PATH.
 */
final class JavaRuntime implements Runtime
{
    public const MAVEN_IMAGE = 'maven:3-eclipse-temurin-21';
    public const GRADLE_IMAGE = 'gradle:8-jdk21';

    /**
     * A Java requirement's version is a JDK release and its build tool, and
     * each entry carries its own `from` through a per-version catalogue
     * override.
     *
     * @var list<string>
     */
    public const TOOLCHAINS = [self::MAVEN, self::GRADLE];

    public const MAVEN = '21-maven';

    public const GRADLE = '21-gradle';

    /**
     * JDK releases, for callers asking about the JDK itself. Not what
     * supportedVersions() answers: no project resolves to bare "17", and both
     * toolchain images are JDK 21.
     *
     * @var list<string>
     */
    public const RELEASES = ['17', '21'];

    /**
     * The toolchains a project may resolve to.
     *
     * @return list<string>
     */
    public static function toolchains(): array
    {
        $configured = RuntimeImageCatalog::versions('java');

        return $configured === [] ? self::TOOLCHAINS : $configured;
    }

    public static function defaultToolchain(): string
    {
        return RuntimeImageCatalog::defaultVersion('java') ?? self::MAVEN;
    }

    /** Builder image for a toolchain: catalogue first, else the constants. */
    public static function imageTag(string $toolchain): string
    {
        $spec = RuntimeImageCatalog::spec('java', $toolchain);
        if ($spec !== null) {
            return $spec->from;
        }

        return str_ends_with($toolchain, '-gradle') ? self::GRADLE_IMAGE : self::MAVEN_IMAGE;
    }

    public function id(): string
    {
        return 'java';
    }

    /**
     * Maven when there is a pom, Gradle otherwise; a project carrying both is
     * Maven's. The same tie-break `java-gradle.yaml` states with `none:
     * pom.xml`: disagreeing put the jar in target/ while the start looked in
     * build/libs.
     */
    public function resolve(ProjectContext $context): ?Requirement
    {
        if ($context->hasFile('pom.xml')) {
            return new Requirement('java', self::MAVEN, '', 'pom.xml');
        }
        if ($context->hasFile('build.gradle') || $context->hasFile('build.gradle.kts')) {
            return new Requirement('java', self::GRADLE, '', 'build.gradle');
        }

        return null;
    }

    public function image(Requirement $requirement): string
    {
        return self::imageTag($requirement->version);
    }

    /**
     * The largest non-`-plain`/`-sources`/`-javadoc` jar wins, resolved at start
     * time; a multi-module reactor's is found in the shallowest directory below
     * (four levels at most), skipping wrapper jars and copy-dependencies. Depth
     * sorts before size.
     */
    public static function startCommand(bool $gradle): string
    {
        $dir = $gradle ? 'build/libs' : 'target';

        return 'jar="$(ls -S ' . $dir . '/*.jar 2>/dev/null'
            . ' | grep -v -- "-plain\.jar$"'
            . ' | grep -v -- "-sources\.jar$"'
            . ' | grep -v -- "-javadoc\.jar$"'
            . ' | head -1)";'
            . ' [ -n "$jar" ] || jar="$(find . -mindepth 2 -maxdepth 4 -name "*.jar"'
            . ' -not -path "./.git/*"'
            . ' -not -path "./gradle/*"'
            . ' -not -path "./.mvn/*"'
            . ' -not -path "*/dependency/*"'
            . ' -not -name "*-wrapper.jar"'
            . ' -printf "%d %s %p\n" 2>/dev/null'
            . ' | grep -v -- "-plain\.jar$"'
            . ' | grep -v -- "-sources\.jar$"'
            . ' | grep -v -- "-javadoc\.jar$"'
            . ' | sort -k1,1n -k2,2nr | head -1 | cut -d" " -f3-)";'
            . ' [ -n "$jar" ] || { echo "PANELALPHA: no runnable jar in ' . $dir . '"; exit 1; };'
            . ' exec java -jar "$jar"';
    }

    /**
     * Builder image for a project directory, for callers only warming an image.
     */
    public static function imageFor(string $projectDir): string
    {
        return self::imageTag(self::toolchainFor($projectDir));
    }

    /**
     * Maven wins a repository that ships both, the order resolve() applies:
     * spring-petclinic ships pom.xml and build.gradle, and disagreeing left it
     * building into target/ while the start command looked in build/libs.
     */
    public static function toolchainFor(string $projectDir): string
    {
        $projectDir = rtrim($projectDir, '/');
        if ($projectDir !== '' && !is_file($projectDir . '/pom.xml')
            && (is_file($projectDir . '/build.gradle') || is_file($projectDir . '/build.gradle.kts'))
        ) {
            return self::GRADLE;
        }

        return self::MAVEN;
    }

    public function defaultRequirement(): Requirement
    {
        return new Requirement('java', self::defaultToolchain(), '', 'engine default');
    }

    /**
     * The toolchains, not RELEASES: these feed provisioning and the prewarm
     * matrix, and ['17', '21'] names neither image anything runs.
     *
     * @return list<string>
     */
    public function supportedVersions(): array
    {
        return self::toolchains();
    }
}
