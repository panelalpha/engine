### Where to get a Git token

- **GitHub**: [Settings → Developer settings → Personal access tokens](https://github.com/settings/tokens) — fine-grained or classic, `repo` scope
- **GitLab**: [Preferences → Access tokens](https://gitlab.com/-/user_settings/personal_access_tokens) — `read_repository` scope
- **Bitbucket**: [Personal settings → App passwords](https://bitbucket.org/account/settings/app-passwords/)

Paste the token below. It is stored **encrypted** on this server, used by the
deploy pipeline to clone your private repository, and never shown back to
anyone — including the assistant that sent you here.