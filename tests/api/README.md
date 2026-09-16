# Engine API tests

Playwright test suite for the PanelAlpha Engine REST API. Plain TypeScript
specs — no Gherkin layer, no code generation step. Everything runs through
`playwright test`.

## Run it

```bash
npm install
npm test
```

That is the whole thing. On the first run `npm test` notices there is no
`env/.env` and writes it. **On the engine host** (`/opt/panelalpha/shared-hosting`)
that needs no questions: it uses this machine's hostname, mints a token with
`pae-artisan` (no SSH to self), checks it against the live API, and continues.
From a laptop it asks which engine to point at, then mints over SSH. Every run
after that goes straight to the tests.

If you would rather set it up explicitly — or point at a second engine — the
same code path has its own command:

```bash
npm run env:setup -- --host engine.example.com
```

It takes `--token` if you already have one, `--profile <name>` to write
`env/.env.<name>` for a second engine, and `--help` for the rest. Without
`--token` it mints one by running artisan inside the engine's `core` container
over SSH; with no SSH access it prints the exact command to run on the host and
waits for you to paste the result.

Nothing else needs configuring. In particular there is no domain to set up — see
[Domains always resolve](#domains-always-resolve).

Common variations:

| Command                        | What it does                                                |
| ------------------------------ | ----------------------------------------------------------- |
| `npm test`                     | Everything: setup, unit specs, and the API suite            |
| `npm test -- tests/users`      | One directory                                               |
| `npm test -- --grep "suspend"` | One test by name                                            |
| `npm run test:smoke`           | Only specs tagged `@smoke` — the critical path              |
| `npm run test:slow`            | Specs tagged `@slow` (CSF disable, full PHP version matrix) |
| `npm run test:unit`            | Pure logic, no engine required — runs offline               |
| `npm run test:setup`           | Re-provision the shared test user and stop                  |
| `npm run test:cli`             | `pae-artisan` on the engine host (`cli` project)            |
| `npm run test:deploy`          | Shared DinD setup plus git-deploy specs                     |
| `npm run test:ui`              | Playwright UI mode, for stepping through a spec             |
| `npm run report`               | Serve the HTML report (reachable from your laptop on a VPS) |

Anything after `--` is passed straight to `playwright test`.

Failures keep a trace. Open it from the report, or with
`npx playwright show-trace .playwright/test-results*/<test>/trace.zip`. API
requests and responses show up in the trace's Network tab.

### Viewing the report on a VPS

`npm run report` binds to `0.0.0.0:9323` and prints a URL with the engine's
public hostname (from `API_BASE_URL`, `hostname -f`, or `REPORT_HOST`) — not
`localhost`, which Playwright would otherwise emit for a bind-all listener.
From your laptop open e.g. `http://engine.example.com:9323` (firewall must
allow 9323). Override with `REPORT_HOST` / `REPORT_PORT`, or pass Playwright
flags through: `npm run report -- --port 9400`. `npx playwright show-report`
skips that rewrite and will still say localhost.

If you would rather not expose the port publicly, tunnel it:

```bash
ssh -L 9323:127.0.0.1:9323 root@engine.example.com
# on the VPS:
npm run report -- --host 127.0.0.1
# on your laptop open http://127.0.0.1:9323
```

## Configuration

`env/.env` holds two values, and `npm run env:setup` writes both:

- `API_BASE_URL` — the Engine API root, e.g. `https://engine.example.com:2011/api/`
- `API_TOKEN` — a bearer token for that engine

`env/.env.example` documents every optional key. CI can skip the file entirely
and pass `API_BASE_URL` and `API_TOKEN` as environment variables instead;
`npm test` uses those when the file is absent, and never prompts when there is
no terminal.

### Domains always resolve

The suite creates real vhosts and then fetches them over HTTP. It does **not**
invent sslip.io names: those are not what the engine returns. A project created
without `domain` is named by `DomainPlan`:

1. the operator's `sites_base_domain`, if set
2. `*.panelalpha.online` when a PanelAlpha tunnel is asked for
3. `{name}.{dashed-ip}.panelalpha.direct` (or `{name}.{cert_domain}`)
4. `{name}.local`

**Default creates omit `domain` and `tunnel`**, so they follow that ladder —
`.online` first, `.direct` if the hub does not allocate. Pass `tunnel: "none"`
to pin `.panelalpha.direct`. `npm test` asks `GET /system/info` for
`default_ipv4` / `cert_domain` / `sites_base_domain` and uses that zone as the
parent for addon names. A hostname in `API_BASE_URL` that maps to `127.0.1.1`
on the engine box is replaced with the engine's own `api_url` and direct zone
— you never set `DOMAIN` for that.

```
API_BASE_URL host 192.0.2.10          ->  addons under 192-0-2-10.panelalpha.direct
API_BASE_URL host engine.example.com  ->  GET /system/info, then that engine's zone
```

Specs that pin a rung live in `tests/tunnels/panelalpha-online.spec.ts`
(default create → `.online`; `tunnel: "none"` → `.direct`).

Everything downstream hangs off that one parent:

- `settings.requireDomain()` returns it, and throws with a clear message when
  neither `API_BASE_URL` nor an explicit `DOMAIN` yields one.
- `randomDomain(base)` requires a base — it has no fallback to a made-up name,
  so the compiler catches a spec that forgets to pass one.
- `userFactory` omits `domain` and reads the name the engine assigned.

A test should therefore never hardcode a domain. Use `setupUser.domain` for the
shared site, or `randomDomain(settings.requireDomain())` for an extra name the
caller itself requests.

Setting `DOMAIN` in `env/.env` overrides the derivation when you manage DNS
yourself. A leftover `*.sslip.io` value is ignored.

### Webserver profiles

`TEST_ENV=nginx` loads `env/.env.nginx` over the base file and keeps that
profile's report, results and setup state separate, so switching profiles does
not invalidate the other's shared user.

## Layout

```
tests/api/
├── tests/            # The specs. One directory per API area.
│   ├── setup/        # Provisions the shared test user (the `setup` project)
│   ├── unit/         # Pure logic against stub transports — needs no engine
│   ├── health/ users/ domains/ php/ cron/ ftp/ sftp/ mysql/ files/
│   ├── wp-cli/ csf/ ip/ modsec/ lighthouse/ system/ exim/
│   ├── permalinks/ lscache/ integration/ inspect/ ssh/
│   ├── cli/          # pae-artisan (Playwright project `cli`)
│   └── deploy/       # git deploy + running DinD app (project `deploy`)
├── fixtures/
│   ├── test-options.ts   # THE import point for every spec
│   └── helper/           # Setup state handed from `setup` to the other projects
├── clients/          # Typed Engine API client, one file per resource
├── schemas/          # Zod schemas for response shapes
├── types/            # Request/response interfaces
├── assertions/       # Reusable multi-step business assertions
├── test-data/
│   ├── factories/    # Build real users, domains, databases via the API
│   └── static/       # Fixed inputs: SSH keys, certificates, upload payloads
├── helpers/          # Retry, randomisation, webserver quirks, protocol clients
├── config/           # Settings and timeout constants
└── env/              # .env.example and your local .env
```

## Conventions

These are the rules the suite is written to. ESLint enforces the mechanical ones.

**Import `test` and `expect` from `@/fixtures/test-options`,** never from
`@playwright/test`. That is what carries the `api`, `settings`, factory and
assertion fixtures. Importing Playwright directly in a spec is a lint error.

**Take what you need as a fixture argument.** Do not construct an `EngineApi` or
a factory by hand; ask for it and let the fixture wire it up.

```ts
test('a suspended user reports as suspended', async ({ api, userFactory, userAssertions }) => {
  const user = await userFactory.createSimpleUser();
  await api.suspendUser(user.username);
  await userAssertions.verifyUserStatus(user.username, 'suspended');
});
```

**Clean up what you create.** `userFactory` deletes its users when the test ends,
so most specs need no teardown at all. A failing test keeps them instead, and
lists them in the report as a `preserved-users` annotation, so the engine can be
inspected. When a user must outlive one test — a `serial` chain, for instance —
create it with `{ autoCleanup: false }` and delete it in the last step. Setup
sweeps leftovers whose username matches `randomUsername()` (`pw` plus 8 hex) —
not every project on `.panelalpha.online` / `.direct`.

**Never sleep.** `waitForTimeout` is banned. Poll for the condition you actually
care about with `waitForCondition` from `@/helpers/retry`, or `expect.poll`.
When a delay really is unavoidable because the webserver needs to reload, take
it from `getWebserverPropagationDelay(slug)` rather than hardcoding a number —
OpenLiteSpeed needs 45s where nginx needs 3s.

**Reach for `expectOneOf` over `toContain`** when several statuses are valid. It
reports the value you actually got instead of the list you allowed.

**Tag sparingly.** `@smoke` marks the handful of tests that prove the engine is
alive. `@slow` marks host-wide work that takes minutes (CSF disable/enable, the
full PHP version matrix, DinD create with `git_repo`) and is left out of
`npm test` — run it with `npm run test:slow`. Everything else is untagged and
runs by default.

**Use `setupUser` for read-only work** against the shared WordPress install, and
`userFactory` whenever the test changes something. A spec that mutates
`setupUser` breaks every spec that runs after it.

## Projects

| Project            | Contents                                                     | Depends on   |
| ------------------ | ------------------------------------------------------------ | ------------ |
| `setup`            | Provisions the shared user + WordPress                       | —            |
| `setup-dind`       | Provisions a shared DinD git-deployed project                | —            |
| `unit`             | Stub-transport logic tests                                   | —            |
| `api`              | The API suite                                                | `setup`      |
| `slow`             | `@slow` specs from the API suite (CSF disable, PHP matrix)   | `setup`      |
| `cli`              | `pae-artisan` wrapper and operator commands                  | —            |
| `deploy`           | Git deploy, container lifecycle, app health                  | `setup-dind` |
| `webserver-change` | Switching the active webserver (tens of minutes)             | —            |
| `update`           | The engine update path                                       | —            |
| `engine-cert`      | Engine TLS certificate dry-run (takes sites offline briefly) | —            |
| `network-mutation` | Live `PUT /system/network-config` (gated)                    | —            |

`webserver-change`, `update`, `cli`, `deploy`, `engine-cert`, `slow` and
`network-mutation` reconfigure the host, wait on a git deploy, or take minutes
of host-wide CSF / PHP work, so they are excluded from the default run and
invoked explicitly:

```bash
npx playwright test --project=webserver-change
npm run test:cli
npm run test:deploy
npm run test:slow
```

## Toolchain

TypeScript 7 (the native compiler) type-checks the suite; Playwright transpiles
the specs itself and never invokes `tsc`.

typescript-eslint cannot run on TypeScript 7 yet — it needs the compiler API,
which returns in 7.1 — so the package installs both, under npm aliases:

```jsonc
"@typescript/native": "npm:typescript@^7.0.2",   // provides `tsc`
"typescript": "npm:@typescript/typescript6@^6.0.2" // provides `tsc6` + the API ESLint needs
```

`npm run typecheck` uses TypeScript 7; `npm run lint` uses the 6.0 API behind
the scenes. Once typescript-eslint supports 7.x, the `typescript` alias goes
away and `@typescript/native` becomes a plain `typescript` dependency.

Run `npm run check` (typecheck + lint + format) before pushing.

## Adding a test

1. Find or create `tests/<area>/<thing>.spec.ts`.
2. `import { expect, test } from '@/fixtures/test-options';`
3. If the endpoint has no client method yet, add one to the matching
   `clients/resources/*.api.ts` rather than calling `api.get()` with a raw path.
4. `npm run check`, then run your spec.
