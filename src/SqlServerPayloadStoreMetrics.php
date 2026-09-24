<?php

namespace XLaravel\Embedding\Driver\SqlServer;

use Illuminate\Support\Facades\DB;
use Throwable;
use XLaravel\Embedding\Contracts\PayloadStoreMetrics;
use XLaravel\Embedding\Models\Embeddable;

class SqlServerPayloadStoreMetrics implements PayloadStoreMetrics
{
    public function snapshot(): array
    {
        $rows = Embeddable::query()->count();
        $bytes = null;
        $dataBytes = null;
        $indexBytes = null;

        try {
            $row = DB::connection(config('embedding.database.connection'))
                ->selectOne(
                    'SELECT
                        SUM(a.total_pages) * 8 * 1024 AS total_bytes,
                        SUM(a.data_pages) * 8 * 1024 AS data_bytes
                     FROM sys.tables t
                     INNER JOIN sys.indexes i ON t.object_id = i.object_id
                     INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id
                     INNER JOIN sys.allocation_units a ON p.partition_id = a.container_id
                     WHERE t.name = ?',
                    [config('embedding.database.embeddables_table', 'embeddables')]
                );

            if ($row !== null) {
                $bytes = isset($row->total_bytes) ? (int) $row->total_bytes : null;
                $dataBytes = isset($row->data_bytes) ? (int) $row->data_bytes : null;

                if ($bytes !== null && $dataBytes !== null) {
                    $indexBytes = max(0, $bytes - $dataBytes);
                }
            }
        } catch (Throwable) {
        }

        return [
            'rows' => $rows,
            'bytes' => $bytes,
            'data_bytes' => $dataBytes,
            'index_bytes' => $indexBytes,
        ];
    }
}
