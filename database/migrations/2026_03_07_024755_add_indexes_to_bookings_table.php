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
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('booking_id', 'idx_booking_id');
            $table->index('booking_pnr', 'idx_booking_pnr');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');
            $table->index('departure_date_time', 'idx_departure_date_time');
            $table->index('org_id', 'idx_org_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_id');
            $table->dropIndex('idx_booking_pnr');
            $table->dropIndex('idx_status');
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_departure_date_time');
            $table->dropIndex('idx_org_id');
        });
    }
};
