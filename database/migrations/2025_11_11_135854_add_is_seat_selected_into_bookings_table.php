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
            $table->boolean('is_seat_selected')->default(false)->comment('After Booking Seat Selected Check In Airblue Case')->after('issued_at');           
            $table->json('extra_selection')->nullable()->after('is_seat_selected');
            $table->double('extra_amount')->nullable()->after('base_fare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
           
            
            $table->dropColumn('is_seat_selected');
            $table->dropColumn('extra_selection');
            $table->dropColumn('extra_amount');
        });
    }
};
