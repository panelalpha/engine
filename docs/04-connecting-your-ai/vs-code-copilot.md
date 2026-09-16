# Connecting VS Code and GitHub Copilot

The engine connects to Copilot's agent mode inside VS Code. Once set up, you ask Copilot Chat to deploy things the same way you would ask any other assistant.

## 1. Get the command on your VPS

```bash
pae connect vscode
```

Run this on your VPS. It creates a token and prints the exact command, with your address and token already filled in.

## 2. Run that command on your computer

Paste what your VPS printed. It looks like this:

```bash
code --add-mcp '{"name":"panelalpha-engine","type":"http","url":"https://<host>:2011/mcp","headers":{"Authorization":"Bearer <token>"}}'
```

This is a single line of JSON wrapped in single quotes. Paste it as one line and keep the single quotes on the outside.

## 3. Confirm it works

Open Copilot Chat in VS Code and **switch it to agent mode**. The engine's abilities are only available there, not in ordinary chat or inline suggestions.

In agent mode, open the tools picker and check `panelalpha-engine` is listed. Then ask:

```text
List the projects on this engine.
```

An empty list on a new engine is a success.

## Troubleshooting

**It registered, then will not connect.**
A self-signed certificate on your engine that your computer does not trust. VS Code is built on Node, so the fix is the same as Claude Code: [Trust a self-signed certificate](claude-code.md#trust-a-self-signed-engine-certificate).

VS Code is normally started from a desktop icon, so it will not pick up a certificate setting you added to `~/.bashrc` or `~/.zshrc`. Either start VS Code from a terminal where the setting is active, or set it at the operating-system level.

The lasting fix is giving the engine a real certificate: [Install](../02-getting-started/install.md#if-connecting-asks-you-to-trust-a-certificate).

**The `code` command is not found.**
VS Code's command-line tool is not installed. Open VS Code, press F1, and run **Shell Command: Install 'code' command in PATH**.

**The command is rejected by the shell.**
The quoting is the usual culprit. On Windows PowerShell, adding the server through VS Code's own MCP settings screen is easier.

**Copilot does not offer anything to do with the engine.**
The chat is not in agent mode. The engine's abilities do not exist in ordinary chat.

**401 Unauthorized.**
The token was truncated, or it is the wrong type. Assistants need a token from `pae connect`, not `pae api:token:create`.

**It connects but can barely do anything.**
The permission settings on your VPS are filtering its abilities. See [Decide what the assistant may do](your-assistant.md#decide-what-the-assistant-may-do).

**I want to start over.**
Open VS Code's MCP settings, remove `panelalpha-engine`, and run the command again with a fresh token.
