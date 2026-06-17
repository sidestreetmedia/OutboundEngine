# Changelog

Progress log for OutboundEngine, by phase. Newest first.

## Phase 4 — Personalization (in progress)

Sequence engine, AI step-copy generation, anti-spam + no-fabrication guardrails,
and a human review queue. _Building now._

## Phase 3 — Lead Pipeline (core done)

- **CSV import** — `leads:import`. Maps messy real-world headers to lead fields,
  normalizes emails (the dedup key), collapses duplicates in-file and against
  existing leads, derives company domain from corporate addresses, records
  per-run counts. One bad row is counted and skipped, never fatal.
- **Email verification** — `leads:verify`. Free, no-spend: address syntax plus a
  DNS check that the domain can receive mail (MX, or A as fallback); disposable
  domains flagged risky. Only `valid` leads become `verified` and thus sendable.
- **Stats** — `leads:stats`.
- Schema: `leads`, `lead_imports`.
- _Deferred to the Apollo increment:_ enrichment (title/industry beyond the CSV)
  and trigger detection — both need paid external data, so they don't ship as
  free stubs.

## Settings

- DB-backed settings store; saved values override `.env`; secrets encrypted at
  rest. Web page at `/settings`, plus `settings:set` / `settings:list`.
- Key resolution for the LLM and sending platforms reads through the store.

## Phase 2 — Product Brain (done)

- **Ingestion** — `product:ingest` (PDF via smalot/pdfparser, docx via
  ZipArchive, txt, html) and `product:ingest-url` (fetch + HTML→text). Each
  source stored with extracted text or a recorded failure.
- **Brain builder** — `product:build-brain`. Sends ingested sources to Claude
  under a strict no-fabrication prompt; saves a structured profile (what_we_do,
  icp, differentiators, problems_solved, proof_points). Proof must cite sources.
- **Library builder** — `product:build-library`. Derives personas (role,
  seniority, OKRs, pains) and a value-prop library mapped to them. Idempotent.
- **Anthropic client** — real Messages API client behind the `LlmClient`
  contract; records token cost on every call via the CostMeter. Falls back to a
  null client (which throws) when no key is set.
- Schema: `products`, `product_sources`, `personas`, `value_props`.

## Phase 1 — Foundation (done)

- Laravel 13 (PHP 8.3) skeleton; fully Dockerized stack (app, web, queue,
  scheduler, mysql, redis) with first-boot auto-install.
- Base schema: `campaigns`, `cost_events`.
- Service layer with contracts (`LlmClient`, `EmailVerifier`, `OutboundProvider`)
  and the real `CostMeter`; Instantly + Lemlist behind one `OutboundManager`.
- **Principles baked in:** optimize on replies not opens; real proof only, never
  fabricated; no autonomous spending (costs are metered, nothing is purchased);
  compliance in the core.
