# 🏋️ Pumpwerk

A personal workout tracker with an AI coach. Log gym sessions — machines, sets, weights, cardio — track progression over time, and get session feedback and next-session plans from an LLM.

Built as a single-user app: no registration, no multi-tenancy, just fast logging on a phone at the gym.

## Features

- **Session logging** — create a workout session, add machines from a catalog, set the weight on an on-screen weight stack (tap a plate to move the pin, toggle an add-on plate), and log cardio entries.
- **Machine history** — pick a machine and see everything you've ever done on it.
- **Progression charts** — one bar per session for each machine, coloured by weight band.
- **AI coach** — plans a session as structured targets that load onto the weight stack, gives feedback with targets for next time, remembers what it learned about you, and falls back to a plain progression rule when it has said nothing. Powered by OpenAI.
- **Gym visitors** — live visitor counts of four Fitnesspark gyms, sampled every 10 minutes, with a typical-day curve and a week heatmap showing when each gym is quiet.
- **PWA** — installable on the home screen for quick access at the gym.

## Stack

| Layer     | Tech |
|-----------|------|
| Backend   | Laravel (PHP ≥ 8.3), SQLite |
| Frontend  | Vue 3 + Inertia.js, Tailwind CSS 4, Vite |
| AI        | OpenAI (`gpt-5.6-terra` by default, configurable via `OPENAI_MODEL`) |
| Fonts     | Bricolage Grotesque (variable, self-hosted via Fontsource) |

Queue, cache, and sessions all use the `database` driver in the same SQLite file — no extra services needed.

## Getting started

Local development uses [DDEV](https://ddev.com):

```bash
git clone <repo-url> pumpwerk && cd pumpwerk
ddev start
ddev composer setup      # install, .env, key, migrate
ddev artisan db:seed     # seeds the login user
ddev npm install
ddev npm run dev
```

Then open https://pumpwerk.ddev.site.

### Configuration

Copy `.env.example` and set:

| Variable         | Purpose |
|------------------|---------|
| `APP_USER_EMAIL` | Email of the single seeded login user |
| `APP_USER_PASSWORD` | Its password. Required outside local (locally it falls back to `password`) |
| `OPENAI_API_KEY` | Enables the AI coach (feedback + plans) |
| `OPENAI_MODEL`   | Optional model override |

## Design notes

- **Weight bands.** Weights are colour-coded light to heavy (green ≤ 70 lb, yellow ≤ 130, blue ≤ 190, red above), like competition plates. The limits and colours live in `resources/js/bands.js`, mirrored as `--color-*` tokens in `resources/css/app.css`.
- **The stack.** `resources/js/Components/WeightStack.vue` shows a window of 11 plates that follows the pin; `resources/js/machineWeight.js` holds the plate maths (20 lb plates from 30, add-ons of 2.3 / 4.5 / 6.8).
- **Motion.** Page changes use the View Transitions API through Inertia (`resources/js/app.js`); everything else is CSS in `app.css` plus two helpers in `resources/js/motion.js`. All of it switches off under `prefers-reduced-motion`.

## Architecture notes

- **Single-user by design.** Models are not scoped by `user_id`. Don't add a second account without adding scoping first.
- **Coach requests run in the background.** The plan/feedback endpoints mark the session as pending, answer immediately and run `App\Jobs\RunCoach` after the response (no queue worker needed). The session page polls until the answer or an error is stored on the session. The job still runs inside the PHP-FPM request, so `config/coach.php`'s timeout (50 s) must stay below the server's 60 s limit.
- **The coach answers in JSON** (OpenAI structured output). `CoachService` then keeps only machines that exist, snaps weights to what the stack can produce (`App\Services\MachineWeights`) and clamps reps and sets, so the app can act on the answer.
- **What the coach sees:** the athlete profile and its own running notes (`coach_profiles`), computed per-machine facts (frequency, recency, trend, stalls), sets per muscle group, and the latest sessions. Machines carry a `muscle_group`, guessed from the name and editable on the machine's history page.
- **Targets** (`App\Services\Progression`): today's plan, else the coach's "next time" from the last feedback, else a progression rule in the athlete's own rep range (default 12–15, set in the coach profile): reach the top on every set, then go up one step.
- `artisan import:v1` imports data from the previous tracker (one-time, kept for reference).
- Prompt/LLM logic lives in `app/Services/CoachService.php`.
- Dates are plain `YYYY-MM-DD`; the client sends its local date when starting a session and parses dates via `resources/js/format.js` (never `new Date('YYYY-MM-DD')`, which is UTC).

## Gym visitor counts

`config/gyms.php` lists the gyms and the public Fitnesspark endpoint. `artisan gyms:poll` stores one count per gym in `gym_samples`; the scheduler runs it every 10 minutes between 06:00 and 23:00 Zurich time (`routes/console.php`). **The server needs the scheduler cron** (`* * * * * php artisan schedule:run`), otherwise counts are only saved when someone opens the app and no pattern builds up. Statistics cover the last 8 weeks; samples older than 180 days are pruned.

## Testing & linting

```bash
ddev artisan test   # PHPUnit feature tests
vendor/bin/pint     # code style
```

## Deployment

Hosted via Laravel Forge with quick-deploy: pushing to `main` deploys automatically (including migrations). See `infra.md` for the full infrastructure picture.
