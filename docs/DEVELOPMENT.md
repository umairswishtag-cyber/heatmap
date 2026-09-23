# Development guide

## Prerequisites

- PHP 8.2+ and Composer 2
- Node.js 20+ and npm 10+
- Docker Desktop for PostgreSQL, Redis, MinIO, Nginx, and the integrated stack

## Integrated stack

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec api php artisan migrate
```

Open the dashboard at `http://localhost:3000`, GraphQL at `http://localhost/graphql`, and MinIO console at `http://localhost:9001`.

The host ports are configured in the root `.env` file. If XAMPP Apache is
using port 80 or a local Next.js process is using port 3000, use values such
as the following and rebuild the dashboard so its browser-facing URLs are
embedded correctly:

```dotenv
HTTP_PORT=8080
DASHBOARD_PORT=3001
MINIO_CONSOLE_PORT=9001
APP_URL=http://localhost:8080
NEXT_PUBLIC_GRAPHQL_URL=http://localhost:8080/graphql
NEXT_PUBLIC_TRACKER_URL=http://localhost:8080/tracker.js
```

Useful lifecycle and troubleshooting commands:

```bash
docker compose up --build -d
docker compose ps -a
docker compose logs --tail=100 dashboard nginx api
docker compose down
```

`minio-init` is a one-shot setup container. `Exited (0)` means it created or
verified the bucket successfully; it is not expected to remain running.

## Individual services

```bash
cd services/api
cp .env.example .env
composer install
php artisan migrate
php artisan serve
php artisan queue:work
```

```bash
cd packages/tracker
npm install
npm run build
```

```bash
cd services/dashboard
cp .env.example .env.local
npm install
npm run dev
```

## Verification

```bash
cd services/api && php artisan test
cd packages/tracker && npm test
cd services/dashboard && npm run lint && npm run build
```

After creating a project, copy the script shown on its installation screen. In local development, ensure the tracked test host exactly matches an allowed project domain.

03362472478

How to run

For next time, open PowerShell and run:

cd D:\xampp\htdocs\cro
docker compose up -d
docker compose ps -a


After code, dependency, or environment changes:
docker compose up --build -d
docker compose exec api php artisan migrate --force


To inspect problems:
docker compose logs --tail=100 dashboard nginx api


To stop everything while keeping database/storage data:
docker compose down
Avoid docker compose down -v unless you intentionally want to delete PostgreSQL, Redis, and MinIO data.