<?php

namespace XLaravel\Embedding\Driver\SqlServer\Tests;

use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Embeddings;
use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\Embedding\EmbeddingServiceProvider;
use XLaravel\Embedding\Driver\SqlServer\SqlServerEmbeddingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            EmbeddingServiceProvider::class,
            SqlServerEmbeddingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlsrv');
        $app['config']->set('database.connections.sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 1433),
            'database' => env('DB_DATABASE', 'embedding_test'),
            'username' => env('DB_USERNAME', 'sa'),
            'password' => env('DB_PASSWORD', 'Str0ng!Pass'),
            'charset' => 'utf8',
            'trust_server_certificate' => true,
        ]);

        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'api_key' => 'fake-api-key-for-testing',
        ]);
        $app['config']->set('ai.default_for_embeddings', 'openai');

        $app['config']->set('embedding.database.connection', 'sqlsrv');
        $app['config']->set('embedding.queue.connection', 'sync');
        $app['config']->set('embedding.similarity.driver', 'sqlsrv');
    }
}
