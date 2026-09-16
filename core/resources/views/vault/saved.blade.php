@extends('vault.layout', ['title' => 'Secret saved'])

@section('card')
    <main class="card done">
        <span class="check" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>

        <h1>Secret saved</h1>

        <p>Your AI agent can use it now. Close this tab and tell your agent you are done.</p>
    </main>
@endsection
