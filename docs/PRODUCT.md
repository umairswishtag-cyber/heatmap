# Product definition

## Vision

PulseCRO is a privacy-first, multi-tenant SaaS product that helps site owners understand behavior and improve conversions without a paid analytics dependency. It records browser events, not video, and reconstructs sessions with rrweb.

## Product principles

- Privacy is a data-ingestion constraint, not a dashboard option added later.
- Every read and write is scoped to a project owned by the authenticated account.
- The tracking SDK must remain asynchronous, resilient, framework-neutral, and small.
- PostgreSQL stores searchable metadata; compressed rrweb chunks live in MinIO.
- Expensive processing is asynchronous through Redis-backed Laravel queues.
- GraphQL is the only application contract for the dashboard and tracker.

## Required capabilities

### Phase 1 — foundation and session replay

- Account registration/login/logout with API tokens
- Multiple projects per account, allowed domains, and public tracking keys
- Installation snippet and verification/last-event state
- Visitor and 30-minute session identity in first-party storage
- Page view, click, scroll, navigation, viewport, and rrweb capture
- Buffered, compressed uploads with retry, offline recovery, and `sendBeacon`
- Chunk persistence in MinIO with metadata in PostgreSQL
- Project-scoped sessions list and replay view

### Phase 2 — behavior analysis

- Click, scroll, and movement heatmaps
- Rage/dead-click classification
- Scroll-depth reports, session filtering, and common journeys

### Phase 3 — conversion analysis

- Funnels, conversions, custom events, forms, and JavaScript errors

### Phase 4 — SaaS maturity

- Advanced reporting, teams, retention, sampling controls, CMS/e-commerce integrations, and notifications

## Privacy rules

Passwords, payment fields, authentication tokens, and marked ignored regions must never enter an upload buffer. The tracker honors `data-cro-mask` and `data-cro-ignore`, masks input values by default, and can respect Do Not Track. Server-side limits, validation, domain checks, rate limiting, and authorization provide a second boundary.

## Success criteria for Phase 1

A new user can register, create a project, paste its script into a site on an allowed domain, receive batched events, see the resulting session in the dashboard, and replay the rrweb stream. Session payload rows do not accumulate in PostgreSQL; only metadata and chunk locations do.
