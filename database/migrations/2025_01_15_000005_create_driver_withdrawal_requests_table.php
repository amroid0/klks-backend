<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE driver_withdrawal_requests ADD INDEX driver_withdrawal_requests_driver_id_status_index (driver_id, status(50))');
        } else {
            Schema::table('driver_withdrawal_requests', function (Blueprint $table) {
                $table->index(['driver_id', 'status'], 'driver_withdrawal_requests_driver_id_status_index');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('driver_withdrawal_requests');
    }
};
