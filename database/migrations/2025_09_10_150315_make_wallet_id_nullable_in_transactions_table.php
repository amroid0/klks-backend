<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $walletForeignKey = null;
        if (DB::getDriverName() === 'mysql') {
            $constraint = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transactions'
                  AND COLUMN_NAME = 'wallet_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            $walletForeignKey = $constraint->CONSTRAINT_NAME ?? null;
        }

        if ($walletForeignKey) {
            DB::statement("ALTER TABLE `transactions` DROP FOREIGN KEY `{$walletForeignKey}`");
        }

        Schema::table('transactions', function (Blueprint $table) {
            // Make wallet_id nullable
            $table->bigInteger('wallet_id')->nullable()->change();
        });

        if ($walletForeignKey) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $walletForeignKey = null;
        if (DB::getDriverName() === 'mysql') {
            $constraint = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transactions'
                  AND COLUMN_NAME = 'wallet_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            $walletForeignKey = $constraint->CONSTRAINT_NAME ?? null;
        }

        if ($walletForeignKey) {
            DB::statement("ALTER TABLE `transactions` DROP FOREIGN KEY `{$walletForeignKey}`");
        }

        Schema::table('transactions', function (Blueprint $table) {
            // Make wallet_id not nullable
            $table->bigInteger('wallet_id')->nullable(false)->change();
        });

        if ($walletForeignKey) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('wallet_id')->references('id')->on('wallets');
            });
        }
    }
};
