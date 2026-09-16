<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Lib\Apis\Cloudflare\CloudflareException;
use App\Lib\Vault\RequestVault;
use App\Models\User;
use App\System\Project\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Per-project settings the engine needs but the project cannot carry in its
 * own files -- a Cloudflare API token, today.
 *
 * Every read and write goes through {@see Settings}, which is what the
 * `project:settings:*` console commands call, so validation, the Cloudflare
 * account check on write and the teardown rules on unset are the same
 * whichever way the value arrives.
 *
 * A secret is never read back: {@see Settings::get()} redacts it, so
 * `set` tells a caller whether the value works and `get` tells it whether one
 * is present, and neither hands it out again.
 */
class ProjectSettingController extends Controller
{
    #[OA\Get(
        path: '/projects/{username}/settings',
        summary: "List a project's settings",
        description: 'Secret values are redacted; `set` says whether a value is present.',
        security: [['bearerAuth' => []]],
        tags: ['Project Settings'],
        parameters: [new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'List of settings', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProjectSetting'))],
            )),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    /**
     * List every known setting for a project.
     */
    public function index(string $username): JsonResponse
    {
        return new JsonResponse(['data' => $this->settingsFor($username)->list()]);
    }

    #[OA\Get(
        path: '/projects/{username}/settings/{key}',
        summary: 'Get one project setting',
        description: 'A secret comes back redacted, never in full.',
        security: [['bearerAuth' => []]],
        tags: ['Project Settings'],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cloudflare-api-token'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The setting', content: new OA\JsonContent(ref: '#/components/schemas/ProjectSetting')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Unknown setting', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    /**
     * Get one setting.
     */
    public function show(string $username, string $key): JsonResponse
    {
        try {
            return new JsonResponse(['data' => $this->settingsFor($username)->get($key)]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['key' => $e->getMessage()]);
        }
    }

    #[OA\Put(
        path: '/projects/{username}/settings/{key}',
        summary: 'Set one project setting',
        description: 'A Cloudflare API token is verified against Cloudflare before it is stored, and the '
            . 'account it belongs to comes back in the response.',
        security: [['bearerAuth' => []]],
        tags: ['Project Settings'],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cloudflare-api-token'),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['value'],
            properties: [new OA\Property(property: 'value', type: 'string', description: 'The value to store.')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Setting stored', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Cloudflare API token saved.'),
                    new OA\Property(property: 'account_id', type: 'string'),
                    new OA\Property(property: 'account_name', type: 'string'),
                ],
            )),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Rejected', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    /**
     * Set one setting.
     */
    public function update(string $username, string $key, Request $request): JsonResponse
    {
        $settings = $this->settingsFor($username);
        $validated = $request->validate(['value' => ['required', 'string']]);

        // Resolved before validation by value, not after: a `vault:<ref>` is a
        // reference, so the rule above is satisfied by the reference itself and
        // the secret only exists from here on. This is the one setting that is
        // a credential rather than a preference -- a Cloudflare token scoped to
        // edit tunnels and DNS -- and without this the tool that takes it is the
        // one place an agent cannot use the vault, which is the opposite of what
        // the vault is for. The key is the vault `type`, by design: only an
        // entry created for `cloudflare-api-token` resolves into
        // `cloudflare-api-token`.
        try {
            $result = $settings->set($key, self::valueFor($key, (string) $validated['value']));
        } catch (\InvalidArgumentException | CloudflareException $e) {
            // An unknown key, or a token Cloudflare itself refused: the
            // caller's input either way.
            throw ValidationException::withMessages(['value' => $e->getMessage()]);
        }

        return new JsonResponse(['data' => $result]);
    }

    /**
     * The value to store: the request's own, or the secret a `vault:<ref>`
     * stands for.
     *
     * Only a *secret* setting is allowed to take a reference. An allowlisted
     * setting that is not a secret is a preference, and preferences may be read
     * back -- `project_setting_get` returns a non-secret value verbatim -- so
     * resolving one from the vault would take a secret the caller deliberately
     * kept out of the transcript and write it somewhere it can be read out
     * again. That would be worse than refusing it.
     *
     * A non-secret setting therefore keeps the literal path and a reference in
     * one is stored as the literal string `vault:...`, which is what it always
     * was. Nothing changes for the settings that exist today: the only
     * allowlisted key is a secret.
     */
    private static function valueFor(string $key, string $value): string
    {
        if (!Settings::isSecret($key)) {
            return $value;
        }

        // The request field is `value`; the vault *type* is the setting key in
        // snake_case. Both halves are forced rather than convenient.
        //
        // One field carries whatever secret the key names, so the type cannot
        // be the field name -- `get('value')` would look for an entry of type
        // `value`, which nothing creates. And it cannot be the key verbatim
        // either: vault types are snake_case (`^[a-z][a-z0-9_]*$`, enforced by
        // the mint endpoint), while setting keys are hyphenated, so
        // `cloudflare-api-token` is not a type the vault will mint. The
        // snake_case name is the same secret under both spellings, which is
        // what `TYPES_WITH_HELP` already assumes.
        //
        // `get()` reads the request field by name, so a request that did not
        // carry `value` at all would resolve to null rather than to the string
        // just validated. Handing over the validated value keeps the two in
        // step, and keeps the literal path identical to what it was.
        $type = str_replace('-', '_', Settings::normalizeKey($key));
        $resolved = RequestVault::get('value', $type);

        return $resolved ?? $value;
    }

    #[OA\Delete(
        path: '/projects/{username}/settings/{key}',
        summary: 'Clear one project setting',
        description: 'Clearing a Cloudflare API token is refused while the project still has Cloudflare '
            . 'tunnels, unless `force` is true, which tears them down first.',
        security: [['bearerAuth' => []]],
        tags: ['Project Settings'],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cloudflare-api-token'),
            new OA\Parameter(name: 'force', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Tear down Cloudflare tunnels that depend on this value.'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Setting cleared', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'success', type: 'boolean', example: true)],
            )),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Refused', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    /**
     * Clear one setting.
     */
    public function destroy(string $username, string $key, Request $request): JsonResponse
    {
        $force = $request->boolean('force');

        try {
            $this->settingsFor($username)->unset($key, $force);
        } catch (\InvalidArgumentException | CloudflareException $e) {
            throw ValidationException::withMessages(['key' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => true]);
    }

    private function settingsFor(string $username): Settings
    {
        $user = User::findByUsername($username);
        if (!$user) {
            abort(new JsonResponse(['message' => 'User not found'], 404));
        }

        return $user->project()->settings();
    }
}
