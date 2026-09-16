{{-- The token in the path is the capability, and the action is root-relative for the same reason as the asset URLs. --}}
<form id="vault-form" method="POST" action="/vault/{{ $token }}" autocomplete="off">
    @csrf

    <div class="field">
        <label class="field-label" for="secret">{{ $label }}</label>
        <textarea id="secret" name="secret" placeholder="{{ $placeholder }}" autofocus required></textarea>
        <span class="hint">{{ $note }}</span>
    </div>

    <button class="button" type="submit">Save secret</button>
</form>
