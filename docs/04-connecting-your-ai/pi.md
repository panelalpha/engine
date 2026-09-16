# Connecting Pi

Pi talks to MCP servers through a small adapter. Install that adapter once, then paste the configuration the engine prints.

## 1. Install the adapter on your computer

```bash
pi install npm:pi-mcp-adapter
```

Restart Pi afterwards. Skip this step if `/mcp` already works in a Pi session.

## 2. Get the configuration on your VPS

```bash
pae connect pi
```

Run this on your VPS. It creates a token and prints a configuration block, with your address and token already filled in.

## 3. Add the engine to Pi

Do this on your own computer. Open `~/.config/mcp/mcp.json` so the engine is available in every project. Create the file if it does not exist. Paste the block your VPS printed.

It looks like this:

```json
{
  "mcpServers": {
    "panelalpha-engine": {
      "url": "https://<host>:2011/mcp",
      "headers": {
        "Authorization": "Bearer <token>"
      }
    }
  }
}
```

If the file already has other servers in it, add `panelalpha-engine` alongside them rather than replacing the whole file.

Putting the file inside a project (`.mcp.json`) risks committing your token to a repository.

## 4. Confirm it works

In Pi, open `/mcp`. `panelalpha-engine` should be listed. Then ask it something harmless:

```text
List the projects on this engine.
```

An empty list on a new engine is a success.

## Troubleshooting

**`/mcp` is not a command, or Pi says it has no MCP adapter.**
The adapter is not installed. Run step 1 and restart Pi.

**It registered, then will not connect.**
A self-signed certificate on your engine that your computer does not trust. Copy the certificate to your computer, then point Pi at it with `caFile` on that server entry:

```bash
scp root@203.0.113.10:/opt/panelalpha/shared-hosting/crt/server.cert ~/panelalpha-engine.cert
```

```json
{
  "mcpServers": {
    "panelalpha-engine": {
      "url": "https://<host>:2011/mcp",
      "headers": {
        "Authorization": "Bearer <token>"
      },
      "caFile": "~/panelalpha-engine.cert"
    }
  }
}
```

The lasting fix is giving the engine a real certificate: [Install](../02-getting-started/install.md#if-connecting-asks-you-to-trust-a-certificate).

**401 Unauthorized.**
The token is truncated, or it is the wrong type. Assistants need a token from `pae connect`, not `pae api:token:create`.

**405 Method Not Allowed.**
The connection is configured as something other than HTTP.

**It connects but can barely do anything.**
The permission settings on your VPS are filtering its abilities. See [Decide what the assistant may do](your-assistant.md#decide-what-the-assistant-may-do).

**I committed my token to a repository.**
Revoke it immediately on your VPS, then create a new one and put it in `~/.config/mcp/mcp.json`:

```bash
pae mcp:token:list
pae mcp:token:revoke <id>
```
