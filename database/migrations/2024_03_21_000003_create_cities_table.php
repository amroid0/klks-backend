<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name',50);
            $table->string('state',50)->nullable();
            $table->string('country',50)->default('Egypt');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 10, 8);
            $table->boolean('status')->default(true);
            $table->string('timezone')->default('Africa/Cairo');
            $table->string('currency')->default('EGP');
            
            // Service Hours
            $table->time('service_start_time')->default('06:00:00');
            $table->time('service_end_time')->default('23:00:00');
            
            // Base Pricing (Default values, can be overridden by ride types)
            $table->decimal('base_distance', 8, 2)->default(3.00); // in km
            $table->decimal('base_price', 8, 2)->default(50.00);
            $table->decimal('price_per_km', 8, 2)->default(12.00);
            $table->decimal('price_per_minute', 8, 2)->default(2.00);
            $table->decimal('minimum_fare', 8, 2)->default(50.00);
            $table->decimal('cancellation_charge', 8, 2)->default(50.00);
            $table->decimal('waiting_charge_per_minute', 8, 2)->default(2.00);
            $table->integer('waiting_time_limit')->default(3); // in minutes
            
            // Commission and Tax
            $table->decimal('commission_rate', 5, 2)->default(20.00); // percentage
            $table->decimal('tax_rate', 5, 2)->default(5.00); // percentage
            
            // Night Charges
            $table->decimal('night_charge_multiplier', 4, 2)->default(1.50);
            $table->time('night_start_time')->default('22:00:00');
            $table->time('night_end_time')->default('06:00:00');
            
            $table->json('meta_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['latitude', 'longitude']);
            $table->unique(['name', 'state', 'country']);
        });


    }

    public function down()
    {
        Schema::dropIfExists('cities');
    }
};
