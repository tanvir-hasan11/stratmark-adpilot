# Stratmark Adpilot live deployment guide

This release is read-only by default, the global kill switch is on, provider sync/report jobs are implemented, and no ad or budget write operation is exposed by the UI. Production data is never replaced with demo data.

## 1. Hosting layout

Use the hosting path `/home/stratmar/marketing_panel` as the application directory. If the host supports a separate document root, point the domain at `/home/stratmar/marketing_panel/public`. If it does not, keep the root `.htaccess` in place and verify that `/src`, `/config`, `/database`, `/bin`, `.env`, and `storage` cannot be downloaded.

Enable HTTPS before creating a real admin session.

## 2. Server requirements

- PHP 8.2 or newer
- MySQL 8 or MariaDB 10.6+
- PDO MySQL, cURL, JSON, fileinfo, and GD or Imagick for future creative processing
- Cron access
- HTTPS certificate
- Writable `storage/` directory (750 directory, 600 state files)

## 3. Deploy files

1. Back up any existing site.
2. Upload the contents of `marketing-panel/`.
3. Keep `.env` outside the public directory when possible.
4. Run `composer install --no-dev --optimize-autoloader` if Composer is available. The built-in autoloader works for the current code if Composer is unavailable.
5. Create `storage/` and make it writable by the PHP process.
6. Import `database/schema.sql` into the new MySQL database.

## 4. Environment

Copy `.env.example` to `.env` and set:

- `APP_ENV=production`
- `APP_URL=https://your-domain.example`
- `APP_KEY` to a long random value
- MySQL host, port, database, user, and password
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD_HASH` generated with `password_hash`, never a plain password
- Meta app version and `META_ACCESS_TOKEN` in a secret store
- Google Ads OAuth access token, `GOOGLE_DEVELOPER_TOKEN`, customer ID, and optional login customer ID
- Telegram bot token and destination chat ID
- `READ_ONLY_MODE=1`
- `GLOBAL_KILL_SWITCH=1`

Never put API keys in browser JavaScript, MySQL raw insight rows, screenshots, logs, or chat messages.

## 5. First boot checklist

1. Open `/health.php`. It must return `ok: true` and no missing extensions.
2. Open `/login.php` over HTTPS.
3. Sign in with the new production admin password.
4. Open Settings and save the brand name, currency, timezone, and logo.
5. Add Meta and Google account IDs. These mappings do not grant access by themselves.
6. Configure server-side provider credentials.
7. Run a read-only sync manually and inspect the database.
8. Confirm Telegram report delivery.
9. Leave read-only mode and the kill switch unchanged until at least one week of data quality checks is complete.

## 6. Cron

Use absolute paths and the hosting PHP binary:

```text
0 6 * * * /usr/local/bin/php /home/stratmar/marketing_panel/bin/sync.php >> /home/stratmar/marketing_panel/storage/sync.log 2>&1
30 6 * * * /usr/local/bin/php /home/stratmar/marketing_panel/bin/report.php >> /home/stratmar/marketing_panel/storage/report.log 2>&1
*/5 * * * * /usr/local/bin/php /home/stratmar/marketing_panel/bin/process.php >> /home/stratmar/marketing_panel/storage/process.log 2>&1
```

Do not schedule write execution until the approval, policy, audit, rollback, and kill-switch tests are complete.

## 7. Provider onboarding

### Meta

Create a server-side token with the minimum read permission needed for the first phase, map the ad account to the correct client, and fetch a small date range first. Verify account time zone, currency, attribution settings, pagination, rate-limit handling, and data freshness.

### Google Ads

Configure OAuth refresh-token storage, developer token, customer ID, and login customer ID when using an MCC. Start with GAQL reporting only. Validate micros-to-currency conversion and account time zone before comparing Meta and Google.

### Telegram

Use a private admin chat, restrict approval users by Telegram ID, and never treat arbitrary message text as approval. Approval must be bound to a short-lived proposal ID and revalidated immediately before any future write operation.

## 8. Safety gates

- Keep `READ_ONLY_MODE=1` for the first release.
- Keep `GLOBAL_KILL_SWITCH=1` until data and permissions are verified.
- Use minimum permissions and separate client credentials.
- Require an approval for every write action.
- Enforce budget ceilings, daily action limits, cooldown periods, idempotency keys, and post-action verification.
- Log actor, target, before/after values, reason, proposal ID, and provider response.
- Maintain a tested rollback path.
- Back up MySQL before schema changes.
- Rotate tokens and admin credentials after deployment.

The application code is hardened for deployment, but go-live still requires staging verification of the real hosting environment, database schema, provider credentials, HTTPS, cron execution, database backups, and provider API responses. Do not disable read-only mode or the kill switch until those checks and an independently tested approval/write implementation exist.
