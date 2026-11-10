# Infra — Pumpwerk

Personal workout tracker (Laravel 12 + Inertia/Vue 3). Audited 2026-08-02, re-verified 2026-08-11.

## Hosting
- **Laravel Forge** on **willow** — `ssh forge@167.233.123.148` (alias `willow` in `~/.zshrc`).
  Hetzner-style VPS also hosting `ernte.dil.uno`, `orbit.dil.uno`, and two on-forge.com sites.
- Site: `/home/forge/pumpwerk.dil.uno`, zero-downtime layout (`releases/` + `current` symlink,
  shared `.env`, `database/`, `storage/` at site root). `pumpwerk.on-forge.com` is a vestigial
  empty site dir from provisioning.
- Server: PHP 8.5.7, Node 22.22.3, nginx-fpm. No sudo for the forge user. Disk: 11 G used of 38 G (31%).

## Deploys
- **Quick-deploy is on**: pushing to `main` on GitHub triggers a Forge deploy. Re-verified
  2026-08-11 — commit `6cc41cb` was live within ~1 min of the push and `public/build/` was
  regenerated in the same release, so the script runs both `npm run build` and `php artisan migrate`
  (the latter confirmed 2026-08-02 by `add_workout_indexes` showing Ran).
- Server clock and logs are UTC; local time is CEST (+2), so release timestamps look 2 h "early".
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
- **Fitnesspark visitor endpoint** — public WordPress AJAX endpoint on `www.fitnesspark.ch`, no key.
  Polled for four gyms every 10 minutes during opening hours (`config/gyms.php`).
- **OpenAI** — AI coach feedback and plans (`gpt-5.6-terra`, overridable via `OPENAI_MODEL`).
  Key in the server's `.env` (`OPENAI_API_KEY`) and in the Forge env editor.
- Stale `ANTHROPIC_API_KEY` still sits in the production `.env` from before the OpenAI switch.
  Unused by the code — remove it from the Forge env editor.

## Cron
- **Needed since the gym visitor feature (2026-11-10):** the Laravel scheduler must run every minute
  (`* * * * * cd /home/forge/pumpwerk.dil.uno/current && php artisan schedule:run`) so `gyms:poll`
  samples visitor counts every 10 minutes. Add it in Forge (Scheduler) once the feature is deployed;
  as of 2026-11-10 it is NOT set up yet (see line below).
- No forge-user crontab; no Laravel scheduler entries. Only system cron (certbot renewals, php, sysstat).

## Backups — ⚠️ NOT COVERED
- **Still true as of 2026-08-11.** `backup-status` reports 5/14 servers covered and lists willow
  under NO BACKUPS CONFIGURED — no `~/.restic-env`, no `~/bin/backup-sites.sh`, no forge crontab.
  The production SQLite file (`database/database.sqlite`, 352 KB, last written 2026-08-11 06:15 UTC)
  is the only copy of all live workout data and is protected by nothing. Same for the other sites
  on willow. Newest snapshot anywhere in the fleet is ~6 h old — the bucket is healthy, willow
  simply isn't in it.
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
- AI endpoints block the request up to 50 s (no queue worker configured). The server kills requests
  at 60 s (`request_terminate_timeout` / `max_execution_time` / `fastcgi_read_timeout`, checked
  2026-11-10), so the timeout in `CoachService` must stay below that. OpenAI errors come back as a
  `coach` validation error shown as a toast.
- Production PHP is 8.5, local is 8.4, composer requires `^8.3` — fine, but test locally before
  relying on 8.5-only behavior.
