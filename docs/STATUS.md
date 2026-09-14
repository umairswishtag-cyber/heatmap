# Delivery status

Last updated: 2026-09-14

Status values: **Done**, **In progress**, **Planned**.

## Current milestone: Phase 1 foundation

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
| Automated API tests | In progress | Core unit/feature coverage is started; expand edge cases |
| End-to-end Docker verification | Planned | Docker is not installed in the current workstation environment |

## Known production gaps before Phase 1 release

- Add persisted-query allowlisting and stricter GraphQL complexity/depth rules.
- Add ingestion idempotency keys, queue dead-letter handling, and object lifecycle policies.
- Exercise PostgreSQL, Redis, MinIO, Nginx, and browser replay together in CI.
- Add account email verification, password reset, token rotation, and team-ready roles.
- Load-test ingestion and establish tracker size/Core Web Vitals budgets.
- Add retention cleanup and consent-mode integration.

## Verification snapshot

Verified on 2026-09-14 in the current workstation:

- Lighthouse GraphQL schema validation: passed
- Laravel test suite: 6 tests, 21 assertions, all passed
- Tracker unit suite: 1 test, all passed
- Tracker minified build: passed (188,742 bytes; 60,719 bytes gzip)
- Next.js ESLint and production build: passed
- Composer and npm security advisories: none reported
- Docker Compose YAML parse: passed
- Integrated container smoke test: not run because Docker is not installed on this workstation

## Next work

1. Run the full Docker smoke test and fix environment-specific integration issues.
2. Add API authorization, origin rejection, chunk processing, and replay tests.
3. Add Playwright coverage for register -> create project -> ingest -> replay.
4. Complete Phase 1 observability and deployment hardening.
5. Only then begin Phase 2 heatmaps and frustration analytics.
