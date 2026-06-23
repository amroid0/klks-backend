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

        $column = DB::selectOne("\n            SELECT COLUMN_TYPE, IS_NULLABLE\n            FROM information_schema.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'zones'\n              AND COLUMN_NAME = 'boundaries'\n            LIMIT 1\n        ");

        if (!$column || empty($column->COLUMN_TYPE)) {
            return;
        }

        if (strtoupper((string) ($column->IS_NULLABLE ?? 'YES')) === 'YES') {
            return;
        }

        $hasSpatialIndex = DB::selectOne("\n            SELECT 1\n            FROM information_schema.STATISTICS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'zones'\n              AND COLUMN_NAME = 'boundaries'\n              AND INDEX_TYPE = 'SPATIAL'\n            LIMIT 1\n        ");

        // MySQL requires SPATIAL-indexed geometry columns to be NOT NULL.
        if (
            $hasSpatialIndex &&
            str_contains(strtolower((string) $column->COLUMN_TYPE), 'polygon')
        ) {
            return;
        }

        DB::statement("ALTER TABLE zones MODIFY boundaries {$column->COLUMN_TYPE} NULL");
    }

    public function down(): void
    {
        // Intentionally no-op to avoid forcing NOT NULL and breaking inserts.
    }
};
