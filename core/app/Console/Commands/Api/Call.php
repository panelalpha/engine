<?php

namespace App\Console\Commands\Api;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Console\Output\OutputInterface;

class Call extends Command
{
    protected $signature = 'api:call {method} {uri} {body=""} {--raw}';
    protected $description = 'Call an internal API route and bypass middleware';

    public function handle(): int
    {
        $method = $this->argument('method');
        if (!is_string($method)) {
            $this->error('invalid argument `--method`');
            return 1;
        }
        $method = strtoupper($method);

        $uri = $this->argument('uri');
        if (!is_string($uri)) {
            $this->error('invalid argument `uri`');
            return 1;
        }

        $body = $this->argument('body');
        if (!is_string($body)) {
            $this->error('invalid argument `body`');
            return 1;
        }

        $admin = (new class extends User {
            protected $table = 'admins';
        })->newInstance();
        assert($admin instanceof Authenticatable);
        Auth::setUser($admin);

        // $request = Request::create("/api$uri", $method, content: $body);
        $request = Request::create("/api$uri", $method, [], [], [], [], $body);
        $request->headers->set('Content-Type', 'application/json');
        App::instance('request', $request);

        $response = Route::dispatch($request);
        $content = $response->getContent();
        if ($content === false) {
            $this->error('failed to receive response content');
            return 1;
        }

        // --raw prints the body and nothing else, so the output can be piped
        // straight into jq. Without it the banner and pretty-printing are kept
        // for the operator reading the terminal.
        if ($this->option('raw')) {
            $this->output->writeln($content, OutputInterface::OUTPUT_RAW);
            return $response->getStatusCode() >= 400 ? 1 : 0;
        }

        $this->info("Response:");

        /** @var mixed $decoded */
        $decoded = json_decode($content);
        if ($decoded) {
            $this->line(json_encode($decoded, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->line($content);
        return 0;
    }
}
