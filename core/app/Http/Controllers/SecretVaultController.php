<?php

namespace App\Http\Controllers;

use App\Lib\Vault\RequestVault;
use App\Models\SecretVaultEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * One-time browser handoff of a secret the API caller will not send.
 *
 * The flow: `create` mints an entry and returns a `vault:<ref>` plus the URL
 * of a form the *customer* opens in their browser and pastes the secret into.
 * The agent then passes `vault:<ref>` in place of the secret in any parameter
 * that accepts one (`git_token` on project_create / source_inspect,
 * `env_vars` values), and {@see \App\Lib\Vault\RequestVault} swaps it for the
 * plaintext at read time.
 *
 * The same raw ref appears in the URL and in the `vault:` value, so no lookup
 * can disagree with another; only its sha-256 is stored, so a database dump
 * contains no live form links. An entry is reusable until `expires_at` and
 * then gone -- reads are counted (`use_count`), not consumed.
 *
 * The secret itself is only ever stored encrypted and is never returned by
 * any of these endpoints; `show` reports whether one is present, which is
 * what an agent needs to know to wait for the paste.
 */
class SecretVaultController extends Controller
{
    private const REF_BYTES = 48;

    #[OA\Post(
        path: '/vault/secrets',
        summary: 'Create a vault slot for a secret that will be pasted in a browser',
        description: "Mints a one-use-per-secret paste slot. Returns `ref` (`vault:<id>`, pass it where the "
            . "secret would go -- e.g. the `git_token` field of project_create or source_inspect) and `url` "
            . "(the form the customer opens and pastes the secret into). The entry is reusable until it "
            . "expires (`expires_in` seconds), then gone; a paste can be repeated while it lives. "
            . "**The secret never passes through the API caller** -- that is this mechanism's whole purpose, "
            . "for agents that must not relay a private-repository token through a conversation.",
        security: [['bearerAuth' => []]],
        tags: ['Secret Vault'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['type'],
            properties: [
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    description: 'The request field the reference will be passed in (e.g. `git_token`, `env_vars`). '
                        . 'Free-form snake_case -- the field the caller will send `vault:<ref>` in. '
                        . 'The paste form shows help from `resources/vault/<type>.md` when that file exists, else the default help.',
                    example: 'git_token'
                ),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Vault entry created', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/SecretVaultEntry'),
                ],
            )),
            new OA\Response(response: 422, description: 'Unknown type', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(Request $request): JsonResponse
    {
        // The type is the request field the ref will be passed in -- free
        // form, by design: any snake_case field an API call wants a vault
        // secret for is valid. Help for the paste form is found by
        // convention (resources/vault/<type>.md, else default.md), not by a
        // list here, so a type nobody planned for still works and still
        // explains itself.
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
        ]);

        // The ref doubles as the form URL token, so it needs browser-URL
        // entropy. Raw is returned exactly once and never stored.
        $ref = Str::random(self::REF_BYTES);

        /** @var SecretVaultEntry */
        $entry = SecretVaultEntry::create([
            'ref_hash' => SecretVaultEntry::hashRef($ref),
            'type' => $validated['type'],
            'expires_at' => now()->addSeconds(SecretVaultEntry::TTL_SECONDS),
        ]);

        return new JsonResponse(['data' => [
            'ref' => RequestVault::PREFIX . $ref,
            'type' => $entry->type,
            'url' => $this->formUrl($ref),
            'status' => $entry->status(),
            'expires_in' => SecretVaultEntry::TTL_SECONDS,
        ]], 201);
    }

    #[OA\Get(
        path: '/vault/secrets',
        summary: 'List vault entries',
        description: 'Every live or recently expired entry, newest first. The secret is never included; '
            . '`status` is pending (no paste yet), filled (usable) or expired.',
        security: [['bearerAuth' => []]],
        tags: ['Secret Vault'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Only entries of this type.'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vault entries', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SecretVaultEntry')),
                ],
            )),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
        ]);

        $query = SecretVaultEntry::query()->orderByDesc('created_at')->limit(100);
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        return new JsonResponse(['data' => $query->get()->map(fn (SecretVaultEntry $e) => $this->format($e))->all()]);
    }

    #[OA\Get(
        path: '/vault/secrets/{ref}',
        summary: 'Get one vault entry by its reference',
        description: 'The status of one entry. `ref` is the full `vault:<id>` value `create` returned.',
        security: [['bearerAuth' => []]],
        tags: ['Secret Vault'],
        parameters: [
            new OA\Parameter(name: 'ref', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vault entry', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/SecretVaultEntry'),
                ],
            )),
            new OA\Response(response: 404, description: 'Unknown reference', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(string $ref): JsonResponse
    {
        $entry = $this->findByRef($ref);

        if ($entry === null) {
            abort(new JsonResponse(['message' => 'Not found'], 404));
        }

        return new JsonResponse(['data' => $this->format($entry)]);
    }

    #[OA\Delete(
        path: '/vault/secrets/{ref}',
        summary: 'Delete a vault entry',
        description: 'Removes the entry and its secret. References to it stop resolving.',
        security: [['bearerAuth' => []]],
        tags: ['Secret Vault'],
        parameters: [
            new OA\Parameter(name: 'ref', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'object')],
            )),
            new OA\Response(response: 404, description: 'Unknown reference', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function destroy(string $ref): JsonResponse
    {
        $entry = $this->findByRef($ref);

        if ($entry !== null) {
            $entry->delete();
        }

        return new JsonResponse(['data' => ['ref' => $ref, 'deleted' => true]]);
    }

    /**
     * The form URL on the engine's own host: the base clients already use
     * (`config('app.url')`, which the certificate scripts keep in sync with
     * the served certificate) plus the path the web route serves. The engine
     * host, never a project domain -- this page belongs to the engine.
     */
    private function formUrl(string $ref): string
    {
        return rtrim((string) config('app.url'), '/') . '/vault/' . $ref;
    }

    /**
     * Path and resolver share one strip: `vault:<id>` from the API, bare `<id>`
     * from the URL -- both accepted, one spelling of the truth.
     */
    private function findByRef(string $ref): ?SecretVaultEntry
    {
        if (str_starts_with($ref, RequestVault::PREFIX)) {
            $ref = substr($ref, strlen(RequestVault::PREFIX));
        }

        return SecretVaultEntry::query()
            ->where('ref_hash', SecretVaultEntry::hashRef($ref))
            ->first();
    }

    /**
     * @return array<string, mixed> everything an agent may know; never the secret
     */
    private function format(SecretVaultEntry $entry): array
    {
        return [
            'ref' => null,             // unknowable: only its hash is stored
            'type' => $entry->type,
            'status' => $entry->status(),
            'used_count' => $entry->use_count,
            'last_used_at' => $entry->last_used_at?->toIso8601String(),
            'created_at' => $entry->created_at?->toIso8601String(),
            'expires_at' => $entry->expires_at->toIso8601String(),
        ];
    }
}