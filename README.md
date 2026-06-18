# OutboundEngine

The intelligence layer for cold email that books meetings.

OutboundEngine does **not** send email. Instantly and Lemlist already do that part well — inbox rotation, warmup, throttling, unsubscribe handling, reply capture. Rebuilding that would be slower, worse, and a risk to your sending domains. OutboundEngine is the brain that wraps around them: it learns your offer, decides who to contact, writes a personalized multi-step sequence for every prospect backed by a *real* proof asset, hands it to your sending platform over the API, reads the replies back, and keeps tuning toward more booked conversations.

Built on Laravel 13 (PHP 8.3), MySQL, and Redis. Fully Dockerized — one image, runs anywhere.

## The pipeline

1. **Product Brain** — upload your decks, case studies, and site; the engine builds a structured profile of what you sell and a value-prop library mapped to the personas you target and the OKRs each of them actually owns.
2. **Lead pipeline** — CSV of emails in, or sourced automatically from Apollo, then dedupe, verify, and enrich with title / industry / company / recent triggers.
3. **Personalization** — one sequence per prospect, each step tied to a single value prop, a single proof asset, and a real trigger. No spray-and-pray mail-merge.
4. **Proof assets** — a personalized landing page rendered per prospect, plus a genuine public-presence audit (their site, SEO, ads, social, tech stack) built from signals anyone can see.
5. **Sync** — push leads and sequences into Instantly or Lemlist, pull replies and bounces back, and let the model sort every reply: interested / objection / not now / out-of-office / unsubscribe.
6. **Experiments** — A/B subjects, body length, CTAs, send times, and step count; track at the segment level; promote winners; kill losers; feed what works back into the next round of copy.

## The numbers we're building for

Target: **10+ positive replies per week** at a **0.5–1% positive-reply rate**. That math means roughly **1,000–2,000 verified prospects contacted per week** — about 200–400 per business day.

Two consequences shape the whole system:

- You need warmed inboxes spread across several dedicated sending domains (never your primary). Instantly handles the rotation; OutboundEngine assumes that infrastructure exists and feeds it.
- Every lead is verified before it's ever sent to. A few percent of bounces tanks sender reputation and takes the entire funnel down with it.

Week one is not ten replies. Warmup and a few iteration cycles come first; the rate is an honest floor once targeting, copy, and deliverability are all pulling their weight.

## Principles (the non-negotiables)

- **Optimize on replies, not opens.** Apple Mail Privacy turned open rates into noise. A positive reply is the only signal that a meeting might actually happen, so that's what every experiment is scored on.
- **Real proof, never fabricated.** Audits are assembled from public information about the prospect. The engine never invents a number, a result, or a metric. One fake stat caught in the wild burns the lead and your name with it.
- **No autonomous spending.** The engine surfaces what each API call costs — enrichment credits, verification, LLM tokens — so nothing is a surprise, but it never purchases anything on its own.
- **Compliance lives in the core.** Suppression lists, unsubscribe handling, and CAN-SPAM identity and footer rules are part of the engine, not a patch added later.

## Stack

- **Laravel 13** (PHP 8.3)
- **MySQL 8** for data, **Redis** for queue, cache, and sessions
- **Queue workers** for ingestion, personalization, audit building, and platform sync
- **Docker Compose** for local development; a single image to deploy anywhere

## Roadmap

| Phase | What lands | Status |
|------:|------------|--------|
| 1 | Foundation — skeleton, Docker stack, base schema, config | ✅ done |
| 2 | Product Brain — uploads, URL ingest, profile builder, persona/OKR/value-prop library | ✅ done |
| 3 | Lead pipeline — CSV import, dedupe, verify | ✅ core done¹ |
| 4 | Personalization — sequence engine, AI step copy, guardrails, human review queue | ✅ done |
| 5 | Proof assets — per-prospect landing pages, public-presence audit + report | ✅ done |
| 6 | Sync — Instantly + Lemlist adapters, reply/bounce ingest, reply classifier, compliance | ✅ done |
| 7 | Experiments + dashboard — variant generator, segment optimization, funnel view | ✅ done |

Plus a **settings page** (`/settings`) for keys and configuration, a **funnel dashboard** (`/dashboard`), and per-prospect **proof pages** (`/p/{token}`).

All seven phases are built, plus Apollo as an automatic lead source (sourcing + enrichment + trigger detection). Bandit auto-optimization remains as its own future increment — it needs the send loop running on live data, and auto-shifting traffic is the kind of autonomous action the no-spend, human-in-the-loop design holds back deliberately.

¹ Enrichment and trigger detection need paid external data (Apollo), so they ship with the Apollo increment rather than as free stubs — consistent with the no-autonomous-spend rule. Done as of the Apollo increment.

## Installation & setup

Two ways to run it: **Docker** (recommended — the full stack, closest to production) or a **local PHP** setup. Pick one, then add your keys.

### Prerequisites

- **Docker path:** Docker Engine 24+ with Compose v2, and Git.
- **Local path:** PHP 8.3+ (extensions: `pdo_mysql`, `redis`, `bcmath`, `intl`, `zip`, `gd`, `mbstring`), Composer 2, Node 20+, and a MySQL 8 + Redis you can point at.

### 1. Get the code

```bash
git clone https://github.com/sidestreetmedia/OutboundEngine.git
cd OutboundEngine
cp .env.example .env
```

### 2a. Run with Docker (recommended)

```bash
docker compose up -d --build       # first boot runs composer install for you
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The stack is `app` (php-fpm), `web` (nginx), `queue`, `scheduler`, `mysql`, and `redis`. Service names match `.env`, and MySQL/Redis come up with working local defaults, so there's nothing else to wire. Open **http://localhost:8080/settings** to add keys, then **/dashboard** for the funnel (change the port with `OE_HTTP_PORT`).

- First boot is slow — the image builds and `composer install` runs once. `queue` and `scheduler` wait for `app` to be healthy (i.e. `vendor/` present), so a few seconds of "waiting" in the logs is normal.
- The product UI (`/dashboard`, `/settings`, `/wins`, `/p/{token}`) is self-contained and needs no front-end build. Only the stock landing page at `/` uses Vite — run `docker compose exec app sh -c "npm install && npm run build"` if you want it; otherwise go straight to `/settings`.
- `vendor/`, `node_modules/`, and `.env` are never committed or baked into the image.

### 2b. Run locally (without Docker)

Point `DB_HOST`/`REDIS_HOST` in `.env` at your MySQL and Redis, then:

```bash
composer setup     # installs deps, writes .env, generates APP_KEY, migrates, builds assets
composer dev       # serves app + queue worker + log tail + vite together, on :8000
```

`composer setup` is a one-shot installer; `composer dev` runs everything at once. Prefer to run the pieces yourself? `php artisan serve`, `php artisan queue:work`, and `php artisan schedule:work`.

### 3. Add your keys

Open `/settings` and add at least an LLM key — an **Anthropic** key for Claude, or switch the **LLM provider** to `google` and paste a free **Google AI Studio** key to run Gemma. Sending (Instantly/Lemlist), Apollo, and HubSpot keys live here too, and a saved value overrides `.env`. Nothing else is required to start — ingestion, CSV import, and MX verification run with no keys and no spend. Full list under [Configuration & keys](#configuration--keys).

### 4. Run your first campaign

Prefix each command with `docker compose exec app` if you're on Docker.

```bash
php artisan product:create "Web Care" --one-liner="Done-for-you website maintenance"
php artisan product:ingest-url web-care https://yoursite.com
php artisan product:build-brain web-care
php artisan product:build-library web-care
php artisan leads:import ~/prospects.csv --campaign=web-care
php artisan leads:verify
php artisan campaign:create "Upstate Dentists" --product=web-care
php artisan sequence:create upstate-dentists --steps=3
php artisan campaign:generate upstate-dentists
php artisan messages:review upstate-dentists
php artisan messages:approve --all --campaign=upstate-dentists
```

Then connect a sending platform and push (`campaign:connect-provider` -> `campaign:push`) and watch replies come back. The full command reference is in [Usage](#usage).

### Verify it's working

- `docker compose ps` shows every service up (the `app` health check passes once `vendor/` is installed).
- `php artisan migrate:status` lists the migrations as run.
- `/dashboard` loads — empty until you import leads, then it fills in as the funnel moves.

### Deploying

The same `Dockerfile` produces a production image. For a real deployment: set `APP_ENV=production` and `APP_DEBUG=false`, generate a fresh `APP_KEY`, point at managed MySQL + Redis, and on each release run `php artisan migrate --force` and cache config/routes (`php artisan config:cache && php artisan route:cache`). Keep the `queue` and `scheduler` processes running alongside the web container.

## Configuration & keys

API keys and integration settings can be entered two ways:

- **Settings page** — visit `/settings` to set everything: the LLM provider + keys (Anthropic for Claude, or Google for free Gemma), sending keys (Instantly, Lemlist), verification, Apollo, and HubSpot (token, the summary-email address, and optional portal id). Secrets are encrypted at rest with your `APP_KEY`; a saved value overrides the matching `.env` entry.
- **`.env`** — set `ANTHROPIC_API_KEY`, `INSTANTLY_API_KEY`, etc. directly. The settings page falls back to these when nothing is saved.
- **CLI** — `php artisan settings:set anthropic_api_key sk-ant-...` and `php artisan settings:list`.

The brain and copy generation need an LLM key — either an Anthropic key (Claude) or, for free, set the **LLM provider** to `google` and drop in a Google AI Studio key to run **Gemma** (`gemma-3-27b-it` by default). Everything else (ingestion, CSV import, MX verification) runs with no keys and no spend.

## Usage

Most of the pipeline is driven by Artisan commands; the web UI adds a funnel dashboard (`/dashboard`), a settings page (`/settings`), and a Wins page (`/wins`) for pushing positive replies to HubSpot.

**Product Brain**

```bash
php artisan product:create "Web Care" --one-liner="Done-for-you website maintenance"
php artisan product:ingest web-care ~/decks/web-care.pdf      # PDF, docx, txt, html
php artisan product:ingest-url web-care https://hellosidestreet.com
php artisan product:build-brain web-care                       # sources → structured profile
php artisan product:build-library web-care                     # profile → personas + value props
php artisan product:list
```

**Lead pipeline**

```bash
php artisan leads:import ~/exports/prospects.csv --campaign=web-care   # normalize, dedupe, derive domain
php artisan leads:verify                                               # syntax + MX/A, no spend
php artisan leads:stats
```

Only `verified` leads are eligible to be contacted — invalid, risky, and unverified addresses never go out.

**Apollo — automatic lead sourcing**

```bash
# 1. Source prospects (FREE — no Apollo credits, no emails yet)
php artisan apollo:search web-care --titles="Owner,Founder" --keywords="med spa,dental" --locations="United States" --limit=50
php artisan apollo:search web-care --titles="Owner" --dry-run        # preview the query without calling Apollo

# 2. Reveal emails + enrich (COSTS 1 Apollo credit per lead — confirmed first)
php artisan apollo:enrich web-care --limit=25                        # asks before spending; --yes to skip the prompt

# 3. Verify deliverability like any other lead
php artisan leads:verify
```

`apollo:search` pulls net-new prospects into a campaign with their name/title/company/domain/LinkedIn — free, because Apollo doesn't charge for search and returns no emails. `apollo:enrich` is the only paid step: it reveals work emails, fills in any missing fields, and derives a real "recently moved into this role" trigger from Apollo's employment history (never invented). It shows the credit cost and asks before spending — run non-interactively without `--yes` and it aborts rather than spend. Set `APOLLO_COST_PER_CREDIT` to your plan's rate so the cost meter shows dollars.

**Personalization**

```bash
php artisan campaign:create "Upstate Dentists" --product=web-care
php artisan sequence:create upstate-dentists --steps=3      # intro + middle + break-up
php artisan campaign:generate upstate-dentists              # one draft email per verified lead × step
php artisan messages:review upstate-dentists                # read the drafts (+ any spam-linter flags)
php artisan messages:approve --all --campaign=upstate-dentists
# or per message:  messages:approve 42   /   messages:reject 42 --note="too generic"
```

Each step leads with a single value prop matched to the lead, cites proof only when it's real, and never fabricates personalization. Generated copy lands as a **draft** — nothing is eligible to send until it's approved.

**Proof assets**

```bash
php artisan audit:build upstate-dentists      # audit each lead's site + write a grounded summary
# then each lead has a personalized proof page at /p/{token}, linked from the email
```

`audit:build` fetches each prospect's public homepage, records real observable signals (HTTPS, meta tags, mobile-friendliness, analytics, platform, social links), and writes a short, honest summary from them — never an invented metric. Each lead gets a personalized page at `/p/{token}` showing those findings, and `campaign:push` passes its URL (`oe_landing_url`) so the sequence can link it.

**Sync — hand off to the sender, read replies back**

```bash
# Connect a campaign to the platform-side campaign you've set up (with sending
# accounts + a sequence that references {{oe_subject_1}} / {{oe_body_1}}, ...)
php artisan campaign:connect-provider upstate-dentists --provider=instantly --campaign-id=abc123
php artisan campaign:push upstate-dentists          # push approved copy for verified leads
php artisan replies:sync upstate-dentists           # pull replies + bounces back
php artisan replies:classify upstate-dentists       # interested / objection / not now / unsubscribe / ...
```

OutboundEngine never sends mail itself — Instantly or Lemlist do, with their own warmup, rotation, and throttling. The engine feeds them approved copy and reads the results back. Bounces and unsubscribes go straight onto a **do-not-contact list** that `campaign:push` enforces, so a suppressed address stays suppressed across future imports (`suppress:add` / `suppress:list` / `suppress:check` for manual control).

**CRM — add positive replies to HubSpot**

```bash
# everyone who replied "interested" lives on the Wins page; flip a toggle to add one
open http://localhost:8080/wins

# or push in bulk from the CLI (optionally scoped to a campaign)
php artisan hubspot:push upstate-dentists        # --all re-pushes, --limit caps
```

When a lead replies positively to the current CTA, the **Wins page** (`/wins`) lists them with their reply and the offer they responded to. The per-contact toggle adds them to HubSpot as a contact — keyed on email, so re-pushing updates instead of duplicating — and drops in a note capturing the campaign and CTA. `hubspot:push` does the same in bulk. Set your HubSpot private-app token on the settings page (or `HUBSPOT_API_KEY`); HubSpot has no per-call charge, so this never touches the cost meter. Every add also emails a summary — who they are, the campaign and CTA they responded to, their reply, and the new HubSpot contact — to `HUBSPOT_NOTIFY_EMAIL` (defaults to craft@joshkuhn.com; leave it blank to turn the emails off).

**Dashboard & experiments**

```bash
# the funnel, in your browser: leads → verified → ... → pushed → replied → positive
open http://localhost:8080/dashboard        # or: php artisan dashboard [campaign]

php artisan segments upstate-dentists --by=value_prop   # what's driving positive replies
php artisan segments upstate-dentists --by=angle        # also: title, industry
php artisan sequence:variants upstate-dentists --step=1  # A/B subject-line variants for a step
```

The dashboard puts the **positive-reply rate** front and centre against the target, with the full funnel, reply breakdown, and a cost meter. `segments` ranks value props, angles, titles, and industries by positive-reply rate so you can lean into what works and retire what doesn't — optimizing on replies, never opens.

## License

Proprietary — © 2026 Sidestreet (Josh Kuhn). All rights reserved.
