# 🏋️ Pumpwerk

A personal workout tracker with an AI coach. Log gym sessions — machines, sets, weights, cardio — track progression over time, and get session feedback and next-session plans from an LLM.

Built as a single-user app: no registration, no multi-tenancy, just fast logging on a phone at the gym.

## Features

- **Session logging** — create a workout session, add exercises against a machine catalog, record sets with a machine-aware weight stepper (lb), and log cardio entries.
- **Machine history** — pick a machine and see everything you've ever done on it.
- **Progression charts** — Chart.js visualizations of your lifts over time.
- **AI coach** — on-demand feedback for a finished session and a plan for the next one (with an optional steering note), powered by OpenAI.
- **PWA** — installable on the home screen for quick access at the gym.

## Stack

| Layer     | Tech |
|-----------|------|
| Backend   | Laravel (PHP ≥ 8.3), SQLite |
| Frontend  | Vue 3 + Inertia.js, Tailwind CSS 4, Vite |
| Charts    | Chart.js |
| AI        | OpenAI (`gpt-5.6-terra` by default, configurable via `OPENAI_MODEL`) |
| Fonts     | Anton + Barlow |

Queue, cache, and sessions all use the `database` driver in the same SQLite file — no extra services needed.

## Getting started

Local development uses [DDEV](https://ddev.com):

```bash
git clone <repo-url> pumpwerk && cd pumpwerk
ddev start
ddev composer setup      # install, .env, key, migrate
ddev artisan db:seed     # seeds the login user
npm install
npm run dev
```

Then open https://pumpwerk.ddev.site.

### Configuration

Copy `.env.example` and set:

| Variable         | Purpose |
|------------------|---------|
| `APP_USER_EMAIL` | Email of the single seeded login user |
| `OPENAI_API_KEY` | Enables the AI coach (feedback + plans) |
| `OPENAI_MODEL`   | Optional model override |

## Architecture notes

- **Single-user by design.** Models are not scoped by `user_id`. Don't add a second account without adding scoping first.
- **AI requests are synchronous** — the feedback/plan endpoints call OpenAI inline (up to ~90 s); there is no queue worker.
- `artisan import:v1` imports data from the previous tracker (one-time, kept for reference).
- Prompt/LLM logic lives in `app/Services/CoachService.php`.

## Testing & linting

```bash
ddev artisan test   # PHPUnit feature tests
vendor/bin/pint     # code style
```

## Deployment

Hosted via Laravel Forge with quick-deploy: pushing to `main` deploys automatically (including migrations). See `infra.md` for the full infrastructure picture.
