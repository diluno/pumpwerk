# Infra — Pumpwerk

Personal workout tracker (Laravel 12 + Inertia/Vue 3). Audited 2026-08-02.

## Hosting
- **Laravel Forge** on **willow** — `ssh forge@167.233.123.148` (alias `willow` in `~/.zshrc`).
  Hetzner-style VPS also hosting `ernte.dil.uno`, `orbit.dil.uno`, and two on-forge.com sites.
- Site: `/home/forge/pumpwerk.dil.uno`, zero-downtime layout (`releases/` + `current` symlink,
  shared `.env`, `database/`, `storage/` at site root). `pumpwerk.on-forge.com` is a vestigial
  empty site dir from provisioning.
- Server: PHP 8.5.7, Node 22, nginx-fpm. No sudo for the forge user.

## Deploys
- **Quick-deploy is on**: pushing to `main` on GitHub triggers a Forge deploy. Verified — commit
  `0836721` (pushed 2026-08-02 shortly after midnight) is live and its migration ran (`migrate:status`
  shows `add_workout_indexes` Ran), so the deploy script includes `php artisan migrate`.
- Deploy script itself lives in the Forge dashboard (not readable from the server shell).
- The release checkout uses an OAuth-token HTTPS remote managed by Forge — never copy that
  remote URL anywhere.

## Domains / DNS / Mail
- `pumpwerk.dil.uno` → 167.233.123.148, TLS via Let's Encrypt (Forge-managed, renewal in
  `/etc/cron.d/letsencrypt-renew-domain-*`).
- DNS for `dil.uno` is on **Cloudflare** (kyle/natasha nameservers); record resolves direct-to-IP
  (grey-cloud).
- Mail: `MAIL_MAILER=log` in production — the app never sends real mail.

## Database
- **SQLite in production**: `/home/forge/pumpwerk.dil.uno/database/database.sqlite` (~224 KB,
  shared across releases). Queue/cache/session all use the `database` driver in the same file.
  No MySQL/MariaDB involved despite Forge defaults.

## Third-party services
- **OpenAI** — AI coach feedback and plans (`gpt-5.6-terra`, overridable via `OPENAI_MODEL`).
  Key in the server's `.env` (`OPENAI_API_KEY`) and in the Forge env editor.

## Cron
- No forge-user crontab; no Laravel scheduler entries. Only system cron (certbot renewals, php, sysstat).

## Backups — ⚠️ NOT COVERED
- **willow is one of the servers with no fleet backups** (no `~/.restic-env`, no
  `~/bin/backup-sites.sh`, no cron). The production SQLite file — the only copy of all live
  workout data — is protected by nothing. Same for the other sites on willow.
- **Caveat when onboarding willow to the fleet convention:** `backup-sites.sh` discovers
  *MySQL-style* databases from `.env` files and uploads from `~/*/storage/assets`. Pumpwerk's
  SQLite file matches neither pattern — after the standard setup, add the site's `database/`
  directory (or a `sqlite3 .backup` dump) to the backup paths explicitly, then verify the file
  appears in the first snapshot.
- Local dev copy (`database/database.sqlite` on the Mac) is gitignored and likewise unprotected;
  it's a secondary copy of older data, not a backup of production.

## Local environment
- DDEV project `pumpwerk` → https://pumpwerk.ddev.site (PHP 8.4, MariaDB container unused —
  local `.env` also uses SQLite). Node 24 on the host for Vite.

## Known quirks
- `artisan import:v1` (`app/Console/Commands/ImportV1.php`) — one-time import from the previous
  tracker; keep for reference.
- Single-user app: no registration, models not user-scoped (intentional — do not add a second
  account without adding `user_id` scoping). Login user seeded with `APP_USER_EMAIL`.
- AI endpoints block the request up to 90 s (no queue worker configured); OpenAI errors surface
  as 502/503 error pages.
- Production PHP is 8.5, local is 8.4, composer requires `^8.3` — fine, but test locally before
  relying on 8.5-only behavior.
