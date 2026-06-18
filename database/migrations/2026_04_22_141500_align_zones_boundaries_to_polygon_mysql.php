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
            $this->dropBoundariesTextIndexIfExists();
            DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NULL");
            $this->createBoundariesSpatialIndexIfMissing();
            return;
        }

        // Existing text/json values cannot be converted directly to geometry safely.
        DB::statement("UPDATE zones SET boundaries = NULL WHERE boundaries IS NOT NULL");
        $this->dropBoundariesTextIndexIfExists();
        DB::statement("ALTER TABLE zones MODIFY boundaries POLYGON NULL");
        $this->createBoundariesSpatialIndexIfMissing();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasTable('zones') || !Schema::hasColumn('zones', 'boundaries')) {
            return;
        }

        $this->dropBoundariesSpatialIndexIfExists();
        DB::statement("ALTER TABLE zones MODIFY boundaries TEXT NULL");
        $this->createBoundariesTextIndexIfMissing();
    }

    private function dropBoundariesTextIndexIfExists(): void
    {
        $index = DB::selectOne("SHOW INDEX FROM zones WHERE Column_name = 'boundaries' AND Key_name = 'zones_boundaries_index'");
        if ($index) {
            DB::statement('ALTER TABLE zones DROP INDEX zones_boundaries_index');
        }
    }

    private function createBoundariesTextIndexIfMissing(): void
    {
        $index = DB::selectOne("SHOW INDEX FROM zones WHERE Column_name = 'boundaries' AND Key_name = 'zones_boundaries_index'");
        if (!$index) {
            DB::statement('ALTER TABLE zones ADD INDEX zones_boundaries_index (boundaries(191))');
        }
    }

    private function createBoundariesSpatialIndexIfMissing(): void
    {
        $index = DB::selectOne("SHOW INDEX FROM zones WHERE Column_name = 'boundaries' AND Key_name = 'zones_boundaries_spatial_index'");
        if (!$index) {
            DB::statement('ALTER TABLE zones ADD SPATIAL INDEX zones_boundaries_spatial_index (boundaries)');
        }
    }
};
