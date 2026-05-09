<?php

namespace XLaravel\Embedding\Driver\SqlServer;

use Illuminate\Support\ServiceProvider;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embedding;
use XLaravel\Embedding\SimilarityManager;

class SqlServerEmbeddingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'embedding-sqlsrv-migrations');
        }

        $this->app->resolving(SimilarityManager::class, function (SimilarityManager $manager) {
            $manager->extend('sqlsrv', fn () => new SqlServerDriver());
        });

        // SQL Server read: CAST the binary VECTOR column back to a JSON-parseable string.
        // We explicitly list columns instead of using * to avoid returning raw binary data.
        Embedding::addGlobalScope('sqlsrv_vector_read', function ($query) {
            $table = config('embedding.database.table');
            $dimensions = (int) config('embedding.dimensions', 1536);
            $query->selectRaw(
                "[{$table}].id, [{$table}].embeddable_type, [{$table}].embeddable_id, [{$table}].slot,
                 CAST([{$table}].vector AS NVARCHAR(MAX)) AS vector,
                 [{$table}].created_at, [{$table}].updated_at"
            );
        });
    }

    public function register(): void
    {
        $this->app->bind(VectorStore::class, SqlServerVectorStore::class);
    }
}
