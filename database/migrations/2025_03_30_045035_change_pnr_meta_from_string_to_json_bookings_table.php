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
        \Illuminate\Support\Facades\DB::statement("UPDATE bookings SET pnr_meta = '{}' WHERE JSON_VALID(pnr_meta) = 0 OR pnr_meta IS NULL");
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('pnr_meta')->change()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            //
        });
    }
};
