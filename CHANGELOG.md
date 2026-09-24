# Changelog

All notable changes to `x-laravel/embedding-sqlsrv-driver` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The package's major version follows `laravel/ai`.

## 1.0.0 - 2026-09-24

Initial release. Requires PHP ^8.3 with `pdo_sqlsrv`, Laravel ^12.0 | ^13.0, `x-laravel/embedding` ^1.0 and SQL Server 2025 or Azure SQL Database.

### Added

- `SqlServerDriver` — `sqlsrv` similarity driver running cosine search in SQL Server with `VECTOR_DISTANCE`. Distance is converted to `similarity_score = 1 - distance`, and the distance cutoff applies only when `threshold > 0.0`. Soft-deleting models are loaded with `withTrashed()`.
- Payload `filter` translation for `similarTo()` / `similarToText()` / `mostSimilar()`: a `whereExists` subquery against `embeddables` using `OPENJSON`. Every condition checks the OPENJSON value type so `34` never matches `"34"`, strings compare with a binary collation and exact length, and filter keys are validated before use.
- `SqlServerVectorStore` — writes embeddings with `MERGE ... WITH (HOLDLOCK)` and `CAST(? AS VECTOR(n))`.
- Vector reads through a global scope on the `Embedding` model that casts the native `VECTOR` column to `NVARCHAR(MAX)`.
- `SqlServerVectorStoreMetrics` and `SqlServerPayloadStoreMetrics` — storage figures from `sys.allocation_units`.
- SQL Server-native migrations with the core package's filenames: `embeddings` with a `VECTOR(n)` column and `embeddables` with a native `JSON` payload column, published under the `embedding-sqlsrv-migrations` tag.
