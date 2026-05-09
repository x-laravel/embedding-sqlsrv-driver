<?php

namespace XLaravel\Embedding\Driver\SqlServer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embedding;

class SqlServerVectorStore implements VectorStore
{
    public function store(Model $model, array $vector, string $slot): Embedding
    {
        $connection = config('embedding.database.connection');
        $table = config('embedding.database.table');
        $morphClass = $model->getMorphClass();
        $key = $model->getKey();
        $dimensions = (int) config('embedding.dimensions', 1536);
        $vectorJson = json_encode($vector);
        $now = now()->toDateTimeString();

        DB::connection($connection)->statement(
            "MERGE [{$table}] WITH (HOLDLOCK) AS target
             USING (VALUES (?, ?, ?)) AS source (embeddable_type, embeddable_id, slot)
             ON target.embeddable_type = source.embeddable_type
                AND target.embeddable_id = source.embeddable_id
                AND target.slot = source.slot
             WHEN MATCHED THEN
               UPDATE SET vector = CAST(? AS VECTOR({$dimensions})), updated_at = ?
             WHEN NOT MATCHED THEN
               INSERT (embeddable_type, embeddable_id, slot, vector, created_at, updated_at)
               VALUES (?, ?, ?, CAST(? AS VECTOR({$dimensions})), ?, ?);",
            [$morphClass, $key, $slot, $vectorJson, $now,
             $morphClass, $key, $slot, $vectorJson, $now, $now]
        );

        $embeddingClass = config('embedding.model');

        return $embeddingClass::where('embeddable_type', $morphClass)
            ->where('embeddable_id', $key)
            ->where('slot', $slot)
            ->first();
    }
}
