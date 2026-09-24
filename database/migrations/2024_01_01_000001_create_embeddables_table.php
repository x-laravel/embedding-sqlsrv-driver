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
        $table = config('embedding.database.embeddables_table', 'embeddables');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->morphs('embeddable');
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id']);
        });

        // Blueprint maps json to NVARCHAR(MAX); the native JSON type needs raw DDL.
        DB::connection($this->getConnection())->statement(
            "ALTER TABLE [{$table}] ADD [payload] JSON NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(config('embedding.database.embeddables_table', 'embeddables'));
    }
};
