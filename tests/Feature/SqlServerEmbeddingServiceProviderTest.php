<?php

namespace XLaravel\Embedding\Driver\SqlServer\Tests\Feature;

use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Driver\SqlServer\SqlServerDriver;
use XLaravel\Embedding\Driver\SqlServer\SqlServerVectorStore;
use XLaravel\Embedding\Driver\SqlServer\SqlServerVectorStoreMetrics;
use XLaravel\Embedding\Driver\SqlServer\Tests\Fixtures\Models\Post;
use XLaravel\Embedding\Driver\SqlServer\Tests\TestCase;
use XLaravel\Embedding\SimilarityManager;

class SqlServerEmbeddingServiceProviderTest extends TestCase
{
    public function test_it_registers_the_sqlsrv_driver(): void
    {
        $manager = app(SimilarityManager::class);

        $this->assertInstanceOf(SqlServerDriver::class, $manager->driver('sqlsrv'));
    }

    public function test_it_can_be_set_as_the_default_driver(): void
    {
        $manager = app(SimilarityManager::class);
        $manager->forgetDrivers();

        config(['embedding.similarity.driver' => 'sqlsrv']);

        $this->assertInstanceOf(SqlServerDriver::class, $manager->driver());
    }

    public function test_it_binds_sqlserver_vector_store(): void
    {
        $this->assertInstanceOf(SqlServerVectorStore::class, app(VectorStore::class));
    }

    public function test_it_stores_and_reads_embedding_via_native_vector(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $this->assertNotNull($post->fresh()->embedding);
        $this->assertIsArray($post->fresh()->embedding->vector);
    }

    public function test_it_binds_sqlserver_vector_store_metrics(): void
    {
        $this->assertInstanceOf(SqlServerVectorStoreMetrics::class, app(VectorStoreMetrics::class));
    }

    public function test_metrics_snapshot_reports_rows_and_byte_sizes(): void
    {
        Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $snapshot = app(VectorStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
        $this->assertIsInt($snapshot['bytes']);
        $this->assertIsInt($snapshot['data_bytes']);
        $this->assertIsInt($snapshot['index_bytes']);
        $this->assertGreaterThan(0, $snapshot['bytes']);
    }
}
