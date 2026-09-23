# PulseCRO

PulseCRO is an open-source, service-oriented conversion-rate optimization platform. The repository contains a Next.js dashboard, a Laravel GraphQL API, a standalone rrweb tracking SDK, and local PostgreSQL/Redis/MinIO infrastructure.

The source of truth for scope and progress is:

- [`docs/PRODUCT.md`](docs/PRODUCT.md) — what we are building
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — service boundaries and data flow
- [`docs/STATUS.md`](docs/STATUS.md) — what exists now and what comes next
- [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) — local setup and test commands
- [`docs/GRAPHQL.md`](docs/GRAPHQL.md) — API operations and authentication

## Repository

```text
services/api/        Laravel 12 + Lighthouse GraphQL
services/dashboard/  Next.js App Router dashboard
packages/tracker/    Standalone browser SDK
infrastructure/      Nginx configuration
docs/                Product and engineering documentation
docker-compose.yml   PostgreSQL, Redis, MinIO and application services
```

## Quick start

1. Copy `.env.example` to `.env`.
2. Run `docker compose up --build -d`.
3. Run `docker compose exec api php artisan migrate`.
4. Open `http://localhost:3000` for the dashboard and `http://localhost/graphql` for GraphQL.

Development email is captured by Mailpit at `http://localhost:8025`. Use the
**Forgot password?** link on the sign-in form, then open the reset email in
Mailpit. Account passwords are one-way hashes and cannot be viewed in XAMPP,
Docker, or PostgreSQL; use the reset flow to replace a forgotten password.

If ports 80, 3000, 8025, or 9001 are already in use, change `HTTP_PORT`,
`DASHBOARD_PORT`, `MAILPIT_PORT`, or `MINIO_CONSOLE_PORT` in `.env`. Keep `APP_URL`,
`NEXT_PUBLIC_GRAPHQL_URL`, and `NEXT_PUBLIC_TRACKER_URL` aligned with
`HTTP_PORT`, then rebuild with `docker compose up --build -d`.

Docker is required for the complete stack. See `docs/DEVELOPMENT.md` for running individual services without Docker.
