# Architecture

## Service boundaries

| Service | Responsibility | State |
| --- | --- | --- |
| Dashboard | Account/project UI, reports, and replay player | Stateless Next.js |
| GraphQL API | Auth, authorization, project management, reads, and ingestion admission | Laravel/PostgreSQL |
| Recording worker | Normalize batches, persist chunks, update session metadata | Laravel queue/Redis |
| Tracker | Capture, privacy filtering, identity, batching, compression, transport | Browser storage |
| Object storage | Immutable gzip event chunks | MinIO/S3 API |
| Edge | TLS/host routing, tracker asset delivery, request limits | Nginx |

These are independently deployable services in one monorepo. Laravel code is further split into domain services and jobs; GraphQL resolvers stay thin.

## GraphQL contract

`POST /graphql` is the sole application endpoint. Account operations use a Sanctum bearer token. `ingestRecording` is public because browser scripts cannot safely hold a secret; it is protected by a public project key, allowed-origin validation, payload limits, and rate limiting.

```text
Customer site -> tracker -> ingestRecording mutation -> Redis queue
                                                    -> worker -> MinIO chunk
                                                             -> PostgreSQL metadata
Dashboard -> authenticated GraphQL queries -----------------> PostgreSQL
Dashboard -> session recordingEvents query -----------------> authorized MinIO read
```

Behavior reports are implemented as project-scoped domain services behind typed GraphQL fields. Journey, form, frustration, error, conversion, funnel, and heatmap services query indexed event metadata and return presentation-ready aggregates; raw rrweb data remains isolated in object storage and is loaded only for replay.

The ingestion mutation accepts compressed base64 gzip data. Keeping ingestion in GraphQL satisfies the single-contract requirement, but it is intentionally isolated from management resolvers so it can later be routed/scaled separately without changing the SDK contract.

## Tenancy and authorization

Ownership is `users -> projects -> all analytics records`. Resolvers never accept an arbitrary project and return it directly; `ProjectAccessService` resolves it through the current user. Jobs derive the project from the validated public key. Database indexes begin with `project_id` on analytics access paths.

## Recording lifecycle

1. The tracker creates a persistent `visitor_id` and a tab-lifetime, inactivity-based `session_id`. Page loads in the same tab remain one recording, while a later visit in a new tab gets a new recording.
2. rrweb and semantic analytics records enter an in-memory buffer after client privacy filtering.
3. Every five seconds or at lifecycle boundaries, the tracker gzip-compresses a batch and invokes `ingestRecording`.
4. The API validates key, origin, encoding, size, and shape, then queues an immutable batch.
5. The worker gzips normalized rrweb records into `recordings/{project}/{session}/{chunk}.json.gz`, saves the object, and updates indexed metadata transactionally.
6. An authorized replay query loads ordered chunks and returns the reconstructed event stream to rrweb-player.

## Planned scaling seams

- Route ingestion traffic to dedicated API replicas.
- Partition sessions/events by project and time.
- Replace replay aggregation with short-lived signed MinIO URLs for very large sessions.
- Add idempotency keys and a dead-letter queue before production traffic.
- Split derived analytics into separate consumers while retaining the GraphQL schema.
