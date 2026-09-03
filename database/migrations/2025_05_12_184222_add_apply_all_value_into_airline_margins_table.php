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
            $table->string('apply_all_value')->nullable()->after('apply_to_all')->comment('Apply All Value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airline_margins', function (Blueprint $table) {
            $table->dropColumn('apply_all_value');
        });
    }
};
