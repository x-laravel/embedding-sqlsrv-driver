# CLAUDE.md — embedding-sqlsrv-driver

This file provides guidance to Claude Code (claude.ai/code) when working with this repository.

## Overview

SQL Server 2025 / Azure SQL native vector driver for `x-laravel/embedding`. Handles both similarity search and vector storage using SQL Server's native VECTOR type.

- **Package name:** `x-laravel/embedding-sqlsrv-driver` — **Namespace:** `XLaravel\Embedding\Driver\SqlServer`
- PHP `^8.3` + `ext-sqlsrv` + `ext-pdo_sqlsrv` + Microsoft ODBC Driver 18, Laravel (illuminate) `^12.0|^13.0`, `x-laravel/embedding ^1.2`
- SQL Server 2025 or Azure SQL Database
- Dev: Orchestra Testbench `^10.0|^11.0`, PHPUnit `^11.0|^12.0`

## Running Tests

```bash
# Build once per PHP version
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run all tests
docker compose --profile php83 up   # PHP 8.3
docker compose --profile php84 up   # PHP 8.4
docker compose --profile php85 up   # PHP 8.5

# Run a single test class or method
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter SqlServerDriverTest
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter test_identical_vector_returns_score_of_one
```

Tests require a live SQL Server 2025 instance — the `sqlsrv` service in `docker-compose.yml` provides it. The `scripts/create-db.php` script creates the `embedding_test` database before running PHPUnit (SQL Server does not auto-create databases). CI runs PHP 8.3–8.5 via `.github/workflows/tests.yml`.

## Source Files (`src/`)

| File | Responsibility |
|------|----------------|
| `SqlServerDriver.php` | Implements `SimilarityDriver`. Builds `1 - VECTOR_DISTANCE('cosine', vector, CAST(? AS VECTOR(n)))` query, loads models via `findMany()`, sets `similarity_score` on each. |
| `SqlServerVectorStore.php` | Implements `VectorStore`. Writes embeddings via `MERGE INTO ... USING DUAL` with `CAST(? AS VECTOR(n))`. |
| `SqlServerVectorStoreMetrics.php` | Implements `VectorStoreMetrics`. Returns `Embedding::count()` for `rows`; aggregates `sys.tables` + `sys.indexes` + `sys.partitions` + `sys.allocation_units` for the byte fields. `index_bytes` is derived as `total - data`. Falls back to `null` byte fields if the user lacks `VIEW DATABASE STATE`. |
| `SqlServerEmbeddingServiceProvider.php` | `register()` binds `VectorStore` → `SqlServerVectorStore` and `VectorStoreMetrics` → `SqlServerVectorStoreMetrics`. `boot()` registers `sqlsrv` similarity driver, adds `CAST(vector AS NVARCHAR(MAX))` global scope to `Embedding` model, loads migration, publishes under `embedding-sqlsrv-migrations` tag. |

## Test Structure (`tests/`)

| Path | Purpose |
|------|---------|
| `TestCase.php` | Base test case. Boots `EmbeddingServiceProvider` + `SqlServerEmbeddingServiceProvider`, sets up SQL Server connection with `trust_server_certificate = true` from env vars, calls `Embeddings::fake()`. |
| `Models/Post.php` | Fixture model using `#[EmbedOn]` and `Embeddable` trait. |
| `database/migrations/` | Creates `posts` table for tests. |
| `Feature/SqlServerDriverTest.php` | Tests similarity search: sort order, threshold, limit, `where` filter, empty results. Uses `setVector()` helper to write known vectors via `CAST(? AS VECTOR(n))`. |
| `Feature/SqlServerEmbeddingServiceProviderTest.php` | Tests driver registration, `VectorStore` binding, and embedding read/write via native VECTOR column. |

## Driver Lifecycle

```
register()
  ├─► app->bind(VectorStore::class, SqlServerVectorStore::class)
  └─► app->bind(VectorStoreMetrics::class, SqlServerVectorStoreMetrics::class)

boot()
  ├─► loadMigrationsFrom(...)
  ├─► publishes([...], 'embedding-sqlsrv-migrations')
  ├─► SimilarityManager::extend('sqlsrv', fn() => new SqlServerDriver())
  └─► Embedding::addGlobalScope('sqlsrv_vector_read', CAST AS NVARCHAR scope)
```

`VectorStore` must be bound in `register()` — before `EmbeddingGenerator` is first resolved by the container.

## Key Design Decisions

**Write — `CAST(? AS VECTOR(n))`:** SQL Server's native VECTOR column cannot be written via a standard `json` cast — `CAST(? AS VECTOR(n))` must appear inside the SQL statement. `SqlServerVectorStore` uses a raw `MERGE INTO` statement instead of Eloquent's `updateOrCreate`.

**Read — `CAST(vector AS NVARCHAR(MAX))` global scope:** SQL Server returns VECTOR columns as binary. The global scope wraps the column in `CAST(... AS NVARCHAR(MAX))` so it arrives as a JSON-parseable string and the `json` cast on `Embedding` model works correctly.

**Upsert — `MERGE INTO`:** SQL Server has no `ON DUPLICATE KEY UPDATE`. `SqlServerVectorStore` uses `MERGE INTO [{table}] WITH (HOLDLOCK) AS target USING (VALUES ...) ...`.

**`VECTOR_DISTANCE` dimensions:** The `{$dimensions}` value is interpolated into SQL as an integer literal — `CAST(? AS VECTOR(n))` requires a literal, not a `?` binding.

**SSL certificate:** SQL Server Docker containers use self-signed certificates. The connection config must include `'trust_server_certificate' => true`. The `scripts/create-db.php` uses `TrustServerCertificate=1` in the DSN.

**Database creation:** SQL Server does not auto-create databases (unlike MySQL). The entrypoint runs `scripts/create-db.php` before PHPUnit to create `embedding_test`.

**Migration:** Uses Laravel's Blueprint for standard columns, then adds the VECTOR column via raw `ALTER TABLE` DDL — Blueprint does not support SQL Server's `VECTOR(n)` type.

## Migration

Publish and run the SQL Server migration **instead of** the core `embedding-migrations`:

```bash
composer require x-laravel/embedding-sqlsrv-driver
php artisan vendor:publish --tag=embedding-sqlsrv-migrations
php artisan migrate
```

## Git Commits

Never create a commit unless the user explicitly requests it.
