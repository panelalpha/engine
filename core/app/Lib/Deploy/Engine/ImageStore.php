<?php

namespace App\Lib\Deploy\Engine;

/**
 * Getting an image to where a build or a `compose up` can use it.
 *
 * Two stores are in play: the **host** store, shared by every account and
 * where an image is pulled or built exactly once, and an **account** store
 * private to one tenant. The engine decides whether that is a stream between
 * two daemons (DinD), a no-op because both halves are the same store (rootless
 * Docker), or a `skopeo copy` (Podman).
 *
 * Every method returns a command for {@see \App\System} to run: `host*` runs on
 * the host, everything else inside the account.
 */
interface ImageStore
{
    /**
     * Build a context-free Dockerfile on the **host** under $tag, once.
     *
     * How the shared bases the engine owns come into existence — nothing is
     * COPYed in, so the Dockerfile is all the input there is. Must be
     * idempotent: a deploy calls it on every run.
     */
    public function hostBuildCommand(string $tag, string $dockerfile): string;

    /**
     * Host store → account store, pulling to the host first if it is not
     * there yet. One Hub round-trip for the whole box, not one per account.
     */
    public function importFromHostCommand(EngineAccount $account, string $image): string;

    /**
     * Host store → account store for an image only the host can produce
     * ({@see hostBuildCommand()}), where a registry pull would only 404.
     */
    public function loadFromHostCommand(EngineAccount $account, string $image): string;

    /**
     * Seed several images at once, never more than $concurrency in flight.
     *
     * One script, not N commands, so a cancelled deploy has a single PID to
     * kill. Returned as a script for `['bash', '-c', …]`.
     *
     * @param list<string> $images
     */
    public function parallelImportCommand(EngineAccount $account, array $images, int $concurrency): string;

    /**
     * Inside the account: every image tag its store holds, one per line, so
     * the caller can ask once instead of once per image.
     *
     * @return list<string>
     */
    public function listImagesArgv(): array;

    /**
     * Inside the account: the id of $image, or empty output when the store
     * does not have it.
     *
     * @return list<string>
     */
    public function imageIdArgv(string $image): array;

    /**
     * Inside the account: pull $image from its registry.
     *
     * @return list<string>
     */
    public function pullArgv(string $image): array;

    /**
     * Inside the account: $image's declared ports as JSON, in the
     * `{"5432/tcp":{}}` shape {@see \App\Lib\Deploy\CacheManager\ImageTransfer::parseExposedPorts()}
     * reads.
     *
     * @return list<string>
     */
    public function imageExposedPortsArgv(string $image): array;

    /**
     * On the host: succeed iff the host store already holds $image, i.e.
     * whether providing it to an account is a stream, not a build.
     *
     * @return list<string>
     */
    public function hostImageInspectArgv(string $image): array;

    /**
     * On the host: fetch $image into the host store.
     *
     * The host is the only place with unNATted access to a registry; a pull of
     * a few hundred megabytes through an account's nested bridge does not
     * reliably finish. Fetched once here, handed to every account that wants
     * it.
     *
     * @return list<string>
     */
    public function hostPullArgv(string $image): array;

    /**
     * On the host: the same port metadata as {@see imageExposedPortsArgv()},
     * read from the host store — what lets an unfamiliar vendor image be
     * classified before any account has it.
     *
     * @return list<string>
     */
    public function hostImageExposedPortsArgv(string $image): array;
}
