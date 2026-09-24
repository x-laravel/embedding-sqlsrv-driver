<?php

namespace XLaravel\Embedding\Driver\SqlServer;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use XLaravel\Embedding\Contracts\SearchRequest;
use XLaravel\Embedding\Contracts\SimilarityDriver;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;

class SqlServerDriver implements SimilarityDriver
{
    /**
     * Search for models similar to the request's query vector using SQL Server VECTOR_DISTANCE.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function search(Model $prototype, SearchRequest $request): Collection
    {
        $morphClass = $prototype->getMorphClass();
        $embeddingClass = config('embedding.model');
        $dimensions = (int) config('embedding.dimensions', 1536);
        $vectorJson = json_encode($request->vector);

        $query = app($embeddingClass)
            ->where('embeddable_type', $morphClass)
            ->where('slot', $request->slot)
            ->selectRaw(
                "embeddable_id, 1 - VECTOR_DISTANCE('cosine', vector, CAST(? AS VECTOR({$dimensions}))) AS similarity_score",
                [$vectorJson]
            )
            ->orderByDesc('similarity_score')
            ->limit($request->limit);

        if ($request->threshold > 0.0) {
            $query->whereRaw(
                "1 - VECTOR_DISTANCE('cosine', vector, CAST(? AS VECTOR({$dimensions}))) >= ?",
                [$vectorJson, $request->threshold]
            );
        }

        if ($request->ids !== null) {
            $query->whereIn('embeddable_id', $request->ids);
        }

        if (! empty($request->filter)) {
            $this->applyPayloadFilter($query, $request->filter);
        }

        $results = $query->get();

        $matchedIds = $results->pluck('embeddable_id')->all();
        $scores = $results->pluck('similarity_score', 'embeddable_id')->all();

        $modelQuery = in_array(SoftDeletes::class, class_uses_recursive($prototype), true)
            ? $prototype::query()->withTrashed()
            : $prototype::query();

        return $modelQuery->findMany($matchedIds)
            ->each(fn ($m) => $m->setAttribute('similarity_score', (float) ($scores[$m->getKey()] ?? 0.0)))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyPayloadFilter($query, array $filter): void
    {
        $embeddingsTable = $query->getModel()->getTable();
        $embeddablesTable = (new EmbeddableRecord())->getTable();

        $query->whereExists(function ($exists) use ($filter, $embeddingsTable, $embeddablesTable) {
            $exists->from($embeddablesTable)
                ->whereColumn("{$embeddablesTable}.embeddable_type", "{$embeddingsTable}.embeddable_type")
                ->whereColumn("{$embeddablesTable}.embeddable_id", "{$embeddingsTable}.embeddable_id");

            foreach ($filter as $key => $value) {
                $this->applyPayloadCondition($exists, $key, $value);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyPayloadCondition($query, string $key, mixed $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid payload filter key [{$key}].");
        }

        if (! is_array($value)) {
            $this->wherePayloadEquals($query, $key, $value);

            return;
        }

        if ($value === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($group) use ($key, $value) {
            foreach ($value as $candidate) {
                $group->orWhere(fn ($branch) => $this->wherePayloadEquals($branch, $key, $candidate));
            }
        });
    }

    /**
     * Match the payload key only when its OPENJSON type equals the filter value's JSON type.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function wherePayloadEquals($query, string $key, mixed $value): void
    {
        // SQL Server neither short-circuits predicates nor counts trailing spaces in =, hence TRY_CAST and DATALENGTH.
        [$condition, $bindings] = match (true) {
            is_int($value), is_float($value) => [
                '[type] = 2 AND TRY_CAST([value] AS float) = CAST(? AS float)',
                [$value],
            ],
            is_bool($value) => [
                "[type] = 3 AND [value] = N'".($value ? 'true' : 'false')."'",
                [],
            ],
            is_string($value) => [
                '[type] = 1 AND [value] = CAST(? AS nvarchar(max)) COLLATE Latin1_General_100_BIN2 AND DATALENGTH([value]) = DATALENGTH(CAST(? AS nvarchar(max)))',
                [$value, $value],
            ],
            $value === null => ['[type] = 0', []],
            default => throw new InvalidArgumentException("Payload filter values must be scalar or arrays of scalars [{$key}]."),
        };

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM OPENJSON(payload) WHERE [key] = ? AND {$condition})",
            [$key, ...$bindings]
        );
    }
}
