# Changelog

Progress log for OutboundEngine, by phase. Newest first.

## Phase 5 — Proof assets (done)

Real, genuine proof — assembled from public information, never fabricated.

- **Public-presence audit** — `PresenceAuditor` fetches a prospect's homepage and
  extracts only observable signals (HTTPS, title, meta description, mobile
  viewport, Open Graph, analytics, tracking pixel, platform, social links,
  structured data, page size). Schema: `audits`.
- **Grounded summary** — `AuditReporter` writes 2-3 honest sentences from those
  findings under a strict no-fabrication prompt; thin findings → thin summary.
- **audit:build** — audits a campaign's leads (resolving each site from the
  company domain), reuses a sibling lead's audit for the same domain instead of
  re-fetching, assigns each lead a landing-page token, and runs findings-only
  when no LLM key is set.
- **Per-prospect landing page** — public `/p/{token}` renders a personalized page:
  the matched value prop, the grounded summary, and the real findings as
  win/gap/info observations, with an honesty footer. `campaign:push` passes
  `oe_landing_url` so the sequence can link it. `leads.public_token`.

## Phase 7 — Experiments + dashboard (done)

The visibility-and-learning layer. Shipped before Phase 5 (Proof assets).

- **Funnel dashboard** — `GET /dashboard` (and a `dashboard` CLI). The funnel as
  cumulative lead stages with proportional bars, the positive-reply rate as the
  hero stat against the target, reply breakdown, cost meter, and a per-campaign
  table. Backed by a `FunnelMetrics` service (funnel / messages / replies / rates
  / cost, per campaign and overall).
- **Segment optimization** — `segments --by=value_prop|angle|title|industry`
  ranks segments by positive-reply rate so winners and losers are obvious. A lead
  spanning several value props is counted in each. Optimizing on replies, not
  opens.
- **Variant generator** — `sequence:variants` produces distinct A/B subject-line
  variants for a step (spam-linted), saved to the step to test in the platform.
- Topbar gains Dashboard / Settings nav.

## Phase 6 — Sync (done)

Shipped ahead of Phase 5 (Proof assets) on purpose: closing the send-and-read
loop is what turns approved copy into real, scored outbound.

- **Provider adapters** — real Instantly (API v2, Bearer auth) and Lemlist (Basic
  auth) adapters behind one `OutboundProvider` contract, with `PushResult` /
  `InboundReply` DTOs. HTTP is isolated per provider; endpoints follow the
  documented APIs and want a smoke-test with live keys before first real use.
- **Push** — `campaign:connect-provider` points a campaign at its platform-side
  campaign; `campaign:push` sends verified leads with approved copy, bundling each
  lead's step copy into `oe_subject_N` / `oe_body_N` variables the sequence
  references. Idempotent; records the provider lead id; flips messages to queued.
- **Reply + bounce ingest** — `replies:sync` pulls replies back, matches them to
  leads, dedupes, tags auto-replies, and suppresses bounced addresses. Schema:
  `replies`.
- **Reply classifier** — `replies:classify` sorts each reply (interested /
  objection / not_now / ooo / unsubscribe / auto_reply / other) and reports the
  positive-reply count — the metric the whole system optimizes for.
- **Compliance** — a `suppressions` do-not-contact list (email or whole domain)
  that bounces, unsubscribes, and manual entries feed; enforced at push so a
  suppressed address stays blocked across re-imports. `suppress:add/list/check`.

## Phase 4 — Personalization (done)

- **Sequences** — `sequence:create` scaffolds a campaign's multi-step template
  (always an intro leading with one value prop, a break-up last, filler angles
  between), with per-step delay, angle, subject hint, and instructions. Schema:
  `sequences`, `sequence_steps`.
- **Value-prop selection** — `ValuePropSelector` enforces the "one value prop per
  message" rule: scores value props by how well their persona matches the lead's
  title/seniority and rotates across steps so each opens with a different angle.
- **AI copy generation** — `campaign:generate`. One draft email per (verified
  lead, step). The model gets only the prospect's real fields, the selected value
  prop, and a proof/trigger _only if real_; the prompt forbids invented facts,
  fake "I saw your post" personalization, hype, and signatures. Verified-leads
  only, idempotent, cost attributed to the lead. Schema: `messages`.
- **Guardrails** — `SpamChecker` flags blasty/over-long copy (warnings, not
  blocks) onto each message for review.
- **Human review queue** — `messages:review` / `messages:approve` /
  `messages:reject`. Nothing is eligible to send until a draft is approved.
- **Campaign CLI** — `campaign:create`, `campaign:list`.

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
