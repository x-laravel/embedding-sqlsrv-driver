<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('embedding.database.connection');
    }

    public function up(): void
    {
        $dimensions = (int) config('embedding.dimensions', 1536);
        $table = config('embedding.database.table');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->morphs('embeddable');
            $table->string('slot', 64)->default('default');
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id', 'slot']);
        });

        // Add VECTOR column via raw DDL — Blueprint does not support SQL Server's VECTOR type.
        DB::connection($this->getConnection())->statement(
            "ALTER TABLE [{$table}] ADD [vector] VECTOR({$dimensions}) NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(config('embedding.database.table'));
    }
};
