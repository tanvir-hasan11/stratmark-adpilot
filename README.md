# Stratmark Adpilot

Shared-hosting-compatible Pure PHP + MySQL marketing operations panel for Meta and Google Ads reporting, Telegram notifications, provider-independent AI, and approval-controlled actions.

## Current functionality

- Optional demo mode is explicitly opt-in with `DEMO_MODE=1` and is never used in production by default.
- Settings page saves local application state, brand name, currency, timezone, provider mappings, and daily notification preference; database-backed insight data is isolated by client.
- Custom PNG, JPG, and WebP logos up to 2 MB can be uploaded from Settings and are served only through an authenticated media route.
- Login, dashboard, workflow pages, mobile navigation, and safe empty states are available locally.
- Sync and report workers now run against configured provider mappings and record job status; see `docs/LIVE_DEPLOYMENT.md` for the complete hosting and credential checklist.

## Current safety posture

- Dashboard requires a session login.
- CSRF protection is enabled for login.
- `READ_ONLY_MODE=1` and `GLOBAL_KILL_SWITCH=1` are the safe defaults.
- No live ad write operation is enabled by this release.
- Credentials must stay in `.env` or the hosting secret store; never commit them.
- API tokens are never displayed in the UI.

## Deploy on shared hosting

1. Point the domain at this project's `public/` directory if the host supports it. Otherwise keep `src/`, `config/`, and `database/` outside the public directory and copy only `public/` into the web root.
2. Copy `.env.example` to `.env` and fill in database and provider values.
3. Generate an admin password hash:

```bash
php -r "echo password_hash('CHANGE_ME', PASSWORD_DEFAULT), PHP_EOL;"
```

Put the result in `ADMIN_PASSWORD_HASH`; do not put a plain password in `.env`.
4. Import `database/schema.sql` into MySQL.
5. Run `composer dump-autoload` locally if Composer is available; the built-in autoloader works without Composer.
6. Verify `/health.php`, then sign in at `/login.php`.
7. Configure cron only after credentials are configured:

```text
0 6 * * * php /path/to/marketing-panel/bin/sync.php
30 6 * * * php /path/to/marketing-panel/bin/report.php
*/5 * * * * php /path/to/marketing-panel/bin/process.php
```

## API setup

Meta requires a read token with the appropriate approved permissions and an account mapping. Google Ads requires OAuth refresh-token setup, developer token, customer ID, and (for manager accounts) login customer ID. The current connector layer is read-only; write permissions should not be granted until the approval and execution layer has been independently tested.

## AI providers

The AI adapter accepts any OpenAI-compatible endpoint via `AI_BASE_URL`, `AI_API_KEY`, and `AI_MODEL`. AI is for explanation and proposal generation only. Deterministic metrics and policy checks must run in PHP before an AI response is shown.
