# Delivery status

Last updated: 2026-09-22

Status values: **Done**, **In progress**, **Planned**.

## Current milestone: Phase 2–3 behavior and conversion analysis

| Area | Status | Notes |
| --- | --- | --- |
| Monorepo and service boundaries | Done | Dashboard, API, tracker, worker, infrastructure |
| GraphQL API foundation | Done | Lighthouse schema; no dashboard REST contract |
| Authentication | Done | Register/login/logout/me with Sanctum tokens |
| Project management | Done | Create/update/list, public key, domain ownership checks |
| Database model | Done | Phase 1 project, visitor, session, page, chunk, and event tables |
| Tracker identity and privacy | Done | First-party IDs, 30-minute timeout, masking/ignore hooks |
| Tracker capture | Done | rrweb, page views, SPA navigation, click/scroll, custom events |
| Reliable transport | Done | Batching, gzip, retry, offline persistence, beacon fallback |
| Recording ingestion | Done | GraphQL mutation, validation service, queued processing |
| MinIO chunk storage | Done | S3-compatible gzip chunk writer and metadata |
| Dashboard shell | Done | Responsive SaaS navigation and overview/session surfaces |
| Live dashboard authentication | Done | Browser token flow and authenticated GraphQL client |
| Session listing | Done | Project-scoped GraphQL query and table |
| Session replay | Done | Authorized chunk aggregation and rrweb-player route |
| Heatmaps | Done | Project-scoped click and scroll maps with page, date, and device filters plus CSV export |
| Funnels | Done | Saved ordered funnels, page/custom-event steps, conversion/drop-off analysis, and median timing |
| Journeys | Done | Common paths, entry/exit rankings, bounce rate, device/date filters, and replay links |
| Forms | Done | Privacy-safe starts, submissions, abandonment, completion rate, and field engagement |
| Frustration | Done | Rage clicks, dead clicks, quick backs, ranked issue clusters, and replay links |
| JavaScript errors | Done | Fingerprinted issues, affected sessions/pages, occurrence counts, and replay links |
| Conversions | Done | Goal totals, rate, value, daily trends, goal ranking, and recent conversion replays |
| Automated API tests | In progress | Core unit/feature coverage is started; expand edge cases |
| End-to-end Docker verification | Done | Images rebuilt, migration applied, routes and tracker smoke-tested through Nginx |

## Known production gaps before Phase 1 release

- Add persisted-query allowlisting and stricter GraphQL complexity/depth rules.
- Add ingestion idempotency keys, queue dead-letter handling, and object lifecycle policies.
- Exercise PostgreSQL, Redis, MinIO, Nginx, and browser replay together in CI.
- Add account email verification, password reset, token rotation, and team-ready roles.
- Load-test ingestion and establish tracker size/Core Web Vitals budgets.
- Add retention cleanup and consent-mode integration.

## Verification snapshot

Verified on 2026-09-21 in the current workstation:

- Lighthouse GraphQL schema validation: passed
- Laravel test suite: 8 tests, 31 assertions, all passed
- Tracker unit suite: 1 test, all passed
- Tracker minified build: passed (188,742 bytes; 60,719 bytes gzip)
- Next.js ESLint and production build: passed
- Composer and npm security advisories: none reported
- Docker Compose YAML parse: passed
- Integrated Docker routes (`/`, `/heatmaps`, `/funnels`, `/tracker.js`): HTTP 200

## Next work

1. Add Playwright coverage for register -> create project -> ingest -> heatmap/funnel analysis.
2. Continue Phase 2 with movement maps and deeper frustration correlation.
3. Continue Phase 3 with configurable conversion goals and revenue attribution.
4. Complete observability, retention, and deployment hardening.
