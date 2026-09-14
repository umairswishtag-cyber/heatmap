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
