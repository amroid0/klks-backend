<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'device_token')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `device_token` TEXT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "users" ALTER COLUMN "device_token" TYPE TEXT');
            DB::statement('ALTER TABLE "users" ALTER COLUMN "device_token" DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite stores string/text dynamically; no schema change required for width expansion.
            return;
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'device_token')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `device_token` VARCHAR(191) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "users" ALTER COLUMN "device_token" TYPE VARCHAR(191)');
            DB::statement('ALTER TABLE "users" ALTER COLUMN "device_token" DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }
    }
};
