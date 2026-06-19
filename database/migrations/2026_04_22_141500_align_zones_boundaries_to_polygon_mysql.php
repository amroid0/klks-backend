<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasTable('zones') || !Schema::hasColumn('zones', 'boundaries')) {
            return;
        }

        $column = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'zones'
              AND COLUMN_NAME = 'boundaries'
            LIMIT 1
        ");

        if (!$column || empty($column->COLUMN_TYPE)) {
            return;
        }

        if (str_contains(strtolower((string) $column->COLUMN_TYPE), 'polygon')) {
            DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NULL");
            return;
        }

        // Existing text/json values cannot be converted directly to geometry safely.
        DB::statement("UPDATE zones SET boundaries = NULL WHERE boundaries IS NOT NULL");
        DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasTable('zones') || !Schema::hasColumn('zones', 'boundaries')) {
            return;
        }

        DB::statement("ALTER TABLE zones MODIFY boundaries TEXT NULL");
    }
};
