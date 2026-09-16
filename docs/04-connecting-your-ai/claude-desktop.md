# Connecting Claude Desktop

Claude Desktop runs Claude Code behind its chat. On your VPS it prints a prompt you paste into Claude Desktop. That installs the same plugin as [Claude Code](claude-code.md).

## 1. Get the prompt on your VPS

```bash
pae connect claude-desktop
```

Run this on your VPS. It creates a token and prints a sentence, with your address and token already filled in.

## 2. Paste it into Claude Desktop

Copy the prompt your VPS printed. Paste it into Claude Desktop. It looks like this:

```text
Add the GitHub marketplace panelalpha/agent-skills and install engine@panelalpha with server_url=https://203.0.113.10:2011/mcp and api_token=<token>. Confirm panelalpha-engine is connected, then list the projects on this engine.
```

**Copy the prompt your VPS printed, not the one above.** `203.0.113.10` is a placeholder address used throughout this guide.

What this does: Claude Desktop installs the PanelAlpha Engine plugin and connects to your engine.

What you should see: it lists the projects on this engine. An empty list on a new engine is a success.

## Troubleshooting

**It registered, then will not connect.**
A self-signed certificate on your engine that your computer does not trust. The fix is the same as Claude Code, and it belongs on your computer, not on the engine: [Trust a self-signed certificate](claude-code.md#trust-a-self-signed-engine-certificate).

**401 Unauthorized.**
The token is wrong, or it is an API token rather than an MCP token. Create one with `pae connect claude-desktop`, not `pae api:token:create`.

**Connected, but it cannot do much.**
The permission settings on your VPS are filtering abilities out. Check with `pae mcp:tool:list` on your VPS: [Decide what the assistant may do](your-assistant.md#decide-what-the-assistant-may-do).
