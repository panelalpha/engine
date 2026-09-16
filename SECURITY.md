# Security Policy

Thank you for helping keep **PanelAlpha Engine** and its users safe. 🔐

An engine host is a **privileged, single-purpose machine**: the engine process can control every container on it, and the installer replaces the machine's resolver and firewall. Because of that, we take security reports seriously and ask you to handle them with care.

---

## Reporting a vulnerability

**Please disclose privately. Do not open a public issue, pull request, or discussion for a security problem.** That would expose users before a fix is available.

Report it through one of these private channels:

- **[manage.panelalpha.com/contact](https://manage.panelalpha.com/contact)** (preferred)

You will get an acknowledgement that your report was received. We'll keep you updated as we investigate and work on a fix.

### What to include

The more of this you can provide, the faster we can reproduce and fix the issue:

- A clear description of the vulnerability and its **impact** (what an attacker could do).
- **Steps to reproduce**, or a proof of concept.
- The **engine version** (`pae system:version`) and the host OS.
- Any relevant logs, requests, or configuration, with secrets and tokens redacted.

---

## What counts as a vulnerability

In scope, for example:

- Remote code execution, privilege escalation, or container escape.
- Authentication or authorization bypass (including MCP tokens and API access).
- Exposure of secrets, tokens, or one account's data to another.
- Anything that lets a hosted app affect the host or other accounts.

**Not** a security report:

- **A failing deploy is not a vulnerability.** That path is [What happens when a deploy fails](docs/02-getting-started/what-happens.md).
- Missing hardening you turned off yourself (e.g. installing with `--no-hardening`, or opening a public port for an app).
- Reports against a host you do not own or have explicit permission to test.

> ⚠️ Note: an MCP token with the default ceiling can delete a hosting account. Limit what the assistant can do **before** you paste a token; see [Connecting your AI](docs/04-connecting-your-ai/your-assistant.md#decide-what-the-assistant-may-do). Handing out an over-privileged token is a configuration mistake, not an engine vulnerability.

---

## Supported versions

Security fixes land on the **latest release**. The safest configuration is always the newest tagged version: the installer defaults to it, and updates are quick:

| Version | Supported |
|---|:---:|
| Latest release | ✅ |
| Older releases | ❌ (please [update](docs/02-getting-started/updating.md)) |

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## Safe harbor

Good-faith security research (testing **against your own engine host**) is welcome. If you follow this policy, disclose privately, and give us reasonable time to fix the issue before going public, we will not pursue action against you and we're happy to credit valid, first reports.

Please do **not** access, modify, or destroy data that is not yours, and do not run tests against hosts you do not control.

---

Thank you for reporting responsibly. 💙
