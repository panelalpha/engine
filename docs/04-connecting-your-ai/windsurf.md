# Connecting Windsurf

Windsurf's current agent (Devin Local) registers the engine through the Devin command-line tool. Older Cascade tabs read a JSON file instead; that path is in troubleshooting below.

## 1. Get the command on your VPS

```bash
pae connect windsurf
```

Run this on your VPS. It creates a token and prints the exact command, with your address and token already filled in.

## 2. Run that command on your computer

Paste what your VPS printed. Two lines: the skills plugin, then MCP. It looks like this:

```bash
devin plugins install panelalpha/agent-skills#engine
devin mcp add -s user -H "Authorization: Bearer <token>" panelalpha-engine https://<host>:2011/mcp
```

The first line installs create and debug skills. `-s user` stores the engine for every project, in `~/.config/devin/mcp_config.json`. Keep the quotes around the header, and keep the word `Bearer` with a single space after it.

## 3. Confirm it works

```bash
devin mcp list
```

`panelalpha-engine` should be listed. Then ask it something harmless:

```text
List the projects on this engine.
```

An empty list on a new engine is a success.

## Troubleshooting

**The `devin` command is not found.**
You are on Cascade, the older Windsurf agent, which has no `devin` command. Open **Settings**, then **Cascade**, then **MCP Servers**, then edit the raw config. Paste this into `~/.codeium/windsurf/mcp_config.json`. The URL field must be `serverUrl`, not `url`:

```json
{
  "mcpServers": {
    "panelalpha-engine": {
      "serverUrl": "https://<host>:2011/mcp",
      "headers": {
        "Authorization": "Bearer <token>"
      }
    }
  }
}
```

Use the token and address from step 1. Save, then press refresh in the MCP panel. Putting this file inside a project risks committing your token to a repository.

**It registered, then will not connect.**
A self-signed certificate on your engine that your computer does not trust. Windsurf is built on Node, so the fix is the same as Claude Code: [Trust a self-signed certificate](claude-code.md#trust-a-self-signed-engine-certificate).

Windsurf is normally launched from a desktop icon, so it will not see a certificate setting you added to `~/.bashrc` or `~/.zshrc`. Either start it from a terminal where the setting is active, or set it at the operating-system level.

The lasting fix is giving the engine a real certificate: [Install](../02-getting-started/install.md#if-connecting-asks-you-to-trust-a-certificate).

**401 Unauthorized.**
The token is truncated, or it is the wrong type. Assistants need a token from `pae connect`, not `pae api:token:create`.

**405 Method Not Allowed.**
The connection is configured as something other than HTTP.

**It connects but can barely do anything.**
The permission settings on your VPS are filtering its abilities. See [Decide what the assistant may do](your-assistant.md#decide-what-the-assistant-may-do).

**I want to start over.**

```bash
devin mcp remove panelalpha-engine
```

On Cascade, delete the `panelalpha-engine` entry from `~/.codeium/windsurf/mcp_config.json` instead.
