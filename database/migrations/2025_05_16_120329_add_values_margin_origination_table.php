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
        Schema::table('marign_originations', function (Blueprint $table) {
            $table->boolean('apply_all')->default(0)->after('airline_margin_id')->comment('Apply All Value');
            $table->string('apply_value')->nullable()->after('apply_all')->comment('Apply All Value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marign_originations', function (Blueprint $table) {
            $table->dropColumn('apply_all');
            $table->dropColumn('apply_value');
        });
    }
};
