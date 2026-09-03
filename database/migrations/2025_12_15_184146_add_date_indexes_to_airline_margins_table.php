<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('airline_margins', function (Blueprint $table) {
            $table->index(['sale_start_continue', 'sale_end_continue'],'airline_margins_sale_date_index');
            $table->index(['travel_start_continue', 'travel_end_continue'],'airline_margins_travel_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airline_margins', function (Blueprint $table) {
            $table->dropIndex('airline_margins_sale_date_index');
            $table->dropIndex('airline_margins_travel_date_index');
        });
    }
};
