<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            // Drop old unique index on (name, state, country)
            $table->dropUnique(['name', 'state', 'country']);

            // Add new unique index that includes deleted_at so soft-deleted rows don't block re-create
            $table->unique(['name', 'state', 'country', 'deleted_at'], 'cities_name_state_country_deleted_at_unique');
        });
    }

    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            // Revert to the previous unique index
            $table->dropUnique('cities_name_state_country_deleted_at_unique');
            $table->unique(['name', 'state', 'country']);
        });
    }
};
