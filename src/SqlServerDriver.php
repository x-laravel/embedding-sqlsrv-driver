<?php

namespace XLaravel\Embedding\Driver\SqlServer;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use XLaravel\Embedding\Contracts\SimilarityDriver;

class SqlServerDriver implements SimilarityDriver
{
    public function search(Model $prototype, array $queryVector, int $limit, float $threshold = 0.0, ?array $ids = null, string $slot = 'default'): Collection
    {
        $morphClass = $prototype->getMorphClass();
        $embeddingClass = config('embedding.model');
        $dimensions = (int) config('embedding.dimensions', 1536);
        $vectorJson = json_encode($queryVector);

        $query = app($embeddingClass)
            ->where('embeddable_type', $morphClass)
            ->where('slot', $slot)
            ->selectRaw(
                "embeddable_id, 1 - VECTOR_DISTANCE('cosine', vector, CAST(? AS VECTOR({$dimensions}))) AS similarity_score",
                [$vectorJson]
            )
            ->orderByDesc('similarity_score')
            ->limit($limit);

        if ($ids !== null) {
            $query->whereIn('embeddable_id', $ids);
        }

        if ($threshold > 0.0) {
            $query->whereRaw(
                "1 - VECTOR_DISTANCE('cosine', vector, CAST(? AS VECTOR({$dimensions}))) >= ?",
                [$vectorJson, $threshold]
            );
        }

        $results = $query->get();

        $matchedIds = $results->pluck('embeddable_id')->all();
        $scores = $results->pluck('similarity_score', 'embeddable_id')->all();

        return $prototype::findMany($matchedIds)
            ->each(fn ($m) => $m->setAttribute('similarity_score', (float) ($scores[$m->getKey()] ?? 0.0)))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }
}
