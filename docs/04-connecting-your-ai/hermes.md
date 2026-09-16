# Connecting Hermes

Hermes registers the engine through its own command-line tool. `--auth header` is required. Hermes cannot take the token on that command, so your VPS also prints a line that writes it to `~/.hermes/.env`.

## 1. Get the command on your VPS

```bash
pae connect hermes
```

Run this on your VPS. It creates a token and prints the exact commands, with your address and token already filled in.

## 2. Run that command on your computer

Paste what your VPS printed. Skills, then the token, then MCP. It looks like this:

```bash
hermes plugins install panelalpha/agent-skills/engine --enable
echo "MCP_PANELALPHA_ENGINE_API_KEY='<token>'" >> ~/.hermes/.env
hermes mcp add panelalpha-engine --url https://<host>:2011/mcp --auth header
```

The first command installs create and debug skills. `--enable` is required or they stay disabled. The second writes the token where Hermes looks it up. `--auth header` is required. Hermes then asks whether this server needs authentication (yes) and whether to enable every tool (yes).

The address in `--url` must be the name the engine's certificate was issued for. A certificate for the bare IP will fail if you use a hostname, and the other way round.

## 3. Confirm it works

```bash
hermes mcp list
```

`panelalpha-engine` should be listed. Then test the connection:

```bash
hermes mcp test panelalpha-engine
```

Then ask it something harmless:

```text
List the projects on this engine.
```

An empty list on a new engine is a success.

## Troubleshooting

**It registered, then will not connect.**
A self-signed certificate on your engine that your computer does not trust. Hermes is not a Node tool, so `NODE_EXTRA_CA_CERTS` does not help. Add the certificate to your operating system's trust store. Copy it first:

```bash
scp root@203.0.113.10:/opt/panelalpha/shared-hosting/crt/server.cert ~/panelalpha-engine.cert
```

The lasting fix is giving the engine a real certificate: [Install](../02-getting-started/install.md#if-connecting-asks-you-to-trust-a-certificate).

**A certificate name error, even with a real certificate.**
The URL does not match the name on the certificate. Use the address from the `MCP URL` line the installer printed, not a different hostname for the same machine.

**It asks for an API key.**
The token line did not land in `~/.hermes/.env`, or Hermes was started before that file was written. Paste the token from your VPS output. Do not add the word `Bearer`; Hermes adds it.

**401 Unauthorized.**
The token is truncated, or it is the wrong type. Assistants need a token from `pae connect`, not `pae api:token:create`.

**405 Method Not Allowed.**
The connection is configured as something other than HTTP.

**It connects but can barely do anything.**
The permission settings on your VPS are filtering its abilities. See [Decide what the assistant may do](your-assistant.md#decide-what-the-assistant-may-do).

**I want to start over.**

```bash
hermes mcp remove panelalpha-engine
```
