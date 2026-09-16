<?php

namespace App\Http\Controllers\Web;

use App\Models\SecretVaultEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The browser side of the vault: the form the customer pastes a secret into.
 *
 * Unauthenticated by design -- the 48-character ref in the URL *is* the
 * capability, single-entry and short-lived, the same trust model as
 * `GET /projects/{username}/app/sso-token`. The routes throttle both verbs,
 * the token alone decides which entry is addressed, and the POST never sends
 * the secret back to the page: a saved confirmation, never a rendered value.
 *
 * Lives on the engine's own host under `routes/web.php` (no /api prefix, no
 * bearer auth), so an MCP agent can hand the URL to a customer as-is.
 *
 * A type with no screen of its own falls back to the generic paste page: the
 * caller names the type, so an unplanned one must still work.
 */
class VaultFormController
{
    private const SCREENS = [
        SecretVaultEntry::TYPE_GIT_TOKEN => 'vault.screens.git-token',
        SecretVaultEntry::TYPE_CLOUDFLARE_API_TOKEN => 'vault.screens.cloudflare',
    ];

    private const DEFAULT_SCREEN = 'vault.screens.generic';

    public function show(string $token): View
    {
        $entry = $this->find($token);

        if ($entry === null) {
            return $this->unavailable(
                'This link is not usable',
                'It does not lead to a secret any more. Ask the assistant that gave it to you for a new one.'
            );
        }

        if ($entry->expired()) {
            return $this->expired($entry);
        }

        return view(self::SCREENS[$entry->type] ?? self::DEFAULT_SCREEN, [
            'token' => $token,
            'entry' => $entry,
            'helpHtml' => SecretVaultEntry::help($entry->type),
            'note' => $this->note($entry),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|View
    {
        $validated = $request->validate([
            'secret' => ['required', 'string', 'max:8192', 'not_in:'],
        ]);

        $entry = $this->find($token);

        if ($entry === null) {
            return $this->unavailable(
                'This link is not usable',
                'It does not lead to a secret any more. Ask the assistant that gave it to you for a new one.'
            );
        }

        if ($entry->expired()) {
            return $this->expired($entry);
        }

        // A paste does not burn the slot; the entry stays reusable until it expires.
        $entry->setSecret($validated['secret']);
        $entry->save();

        return view('vault.saved');
    }

    /** Unknown and expired both land here, and neither says which. */
    private function unavailable(string $heading, string $message): View
    {
        return view('vault.screens.unavailable', [
            'heading' => $heading,
            'message' => $message,
        ]);
    }

    private function expired(SecretVaultEntry $entry): View
    {
        return $this->unavailable(
            'This link expired',
            'It expired at ' . $entry->expires_at->format('Y-m-d H:i T') . '. Ask the assistant that gave it to you for a new one.'
        );
    }

    /** A second visit has to say the save replaces what is already there. */
    private function note(SecretVaultEntry $entry): string
    {
        $stored = 'Stored encrypted on your server and never shown to anyone again, including your AI agent.';

        if ($entry->filled_at === null) {
            return $stored;
        }

        return 'A secret is already stored for this link -- saving again replaces it. ' . $stored;
    }

    private function find(string $token): ?SecretVaultEntry
    {
        return SecretVaultEntry::query()
            ->where('ref_hash', SecretVaultEntry::hashRef($token))
            ->first();
    }
}
