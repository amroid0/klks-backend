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

        // 1. Drop any existing index on 'boundaries' to unlock the column modification
        $indexes = DB::select("SHOW INDEX FROM zones WHERE Column_name = 'boundaries'");
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE zones DROP INDEX {$index->Key_name}");
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

        // 2. Change column to POLYGON NOT NULL (Spatial indexes require NOT NULL)
        if (str_contains(strtolower((string) $column->COLUMN_TYPE), 'polygon')) {
            DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NOT NULL");
        } else {
            DB::statement("UPDATE zones SET boundaries = NULL WHERE boundaries IS NOT NULL");
            DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NOT NULL");
        }

        // 3. Re-apply the high-speed spatial routing index
        DB::statement("ALTER TABLE zones ADD SPATIAL INDEX zones_boundaries_spatial (boundaries)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasTable('zones') || !Schema::hasColumn('zones', 'boundaries')) {
            return;
        }

        $indexes = DB::select("SHOW INDEX FROM zones WHERE Column_name = 'boundaries'");
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE zones DROP INDEX {$index->Key_name}");
        }

        DB::statement("ALTER TABLE zones MODIFY boundaries TEXT NULL");
    }
};