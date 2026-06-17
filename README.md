# OutboundEngine

The intelligence layer for cold email that books meetings.

OutboundEngine does **not** send email. Instantly and Lemlist already do that part well — inbox rotation, warmup, throttling, unsubscribe handling, reply capture. Rebuilding that would be slower, worse, and a risk to your sending domains. OutboundEngine is the brain that wraps around them: it learns your offer, decides who to contact, writes a personalized multi-step sequence for every prospect backed by a *real* proof asset, hands it to your sending platform over the API, reads the replies back, and keeps tuning toward more booked conversations.

Built on Laravel 13 (PHP 8.3), MySQL, and Redis. Fully Dockerized — one image, runs anywhere.

## The pipeline

1. **Product Brain** — upload your decks, case studies, and site; the engine builds a structured profile of what you sell and a value-prop library mapped to the personas you target and the OKRs each of them actually owns.
2. **Lead pipeline** — CSV of emails in (Apollo API as an automatic source later), then dedupe, verify, and enrich with title / industry / company / recent triggers.
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
| 5 | Proof assets — per-prospect landing pages, public-presence audit + report | 🛠 next |
| 6 | Sync — Instantly + Lemlist adapters, reply/bounce ingest, reply classifier, compliance | ◻ planned |
| 7 | Experiments + dashboard — variant generator, segment optimization, funnel view | ◻ planned |

Plus a **settings page** (`/settings`) for entering and storing API keys and configuration.

¹ Enrichment and trigger detection need paid external data (Apollo, firmographic/news APIs), so they ship with the Apollo increment rather than as free stubs — consistent with the no-autonomous-spend rule.

Apollo as an automatic lead source and bandit auto-optimization follow as their own increments once the core loop is running.

## Local setup

Requires Docker. The stack is `app` (php-fpm), `web` (nginx), `queue`, `scheduler`, `mysql`, and `redis`.

```bash
cp .env.example .env
docker compose up -d --build      # first boot installs vendor/ automatically
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Then open **http://localhost:8080** (change the port with `OE_HTTP_PORT` in `.env`).

Notes:
- First boot is slow — the image builds and `composer install` runs once. The `queue` and `scheduler` containers wait for that to finish before starting, so a few seconds of "waiting" in the logs is expected.
- `vendor/`, `node_modules/`, and `.env` are never committed or baked into the image.
- The same `Dockerfile` produces a standalone image for deploying anywhere — no local-only assumptions.

## Configuration & keys

API keys and integration settings can be entered two ways:

- **Settings page** — visit `/settings`, enter your keys (Anthropic, Instantly, Lemlist, verification, Apollo). Secrets are encrypted at rest with your `APP_KEY`; a saved value overrides the matching `.env` entry.
- **`.env`** — set `ANTHROPIC_API_KEY`, `INSTANTLY_API_KEY`, etc. directly. The settings page falls back to these when nothing is saved.
- **CLI** — `php artisan settings:set anthropic_api_key sk-ant-...` and `php artisan settings:list`.

The brain and copy generation need an Anthropic key; everything else (ingestion, CSV import, MX verification) runs with no keys and no spend.

## Usage

The pipeline is driven by Artisan commands today; the dashboard arrives in Phase 7.

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

## License

Proprietary — © 2026 Sidestreet (Josh Kuhn). All rights reserved.
