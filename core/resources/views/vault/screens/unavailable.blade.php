@extends('vault.layout', ['title' => 'Link unavailable'])

@section('card')
    <main class="card">
        <h1 class="title">
            {{-- `git.svg`, not `git-white.svg`: the tile behind it is white. --}}
            <span class="tile"><span style="width:24px;height:24px;background-image:url('/vault/icons/git.svg')"></span></span>
            {{ $heading ?? 'This link is not usable' }}
        </h1>

        <p class="lede">{{ $message ?? 'It has expired. Ask the assistant that gave it to you for a new one.' }}</p>
    </main>
@endsection
