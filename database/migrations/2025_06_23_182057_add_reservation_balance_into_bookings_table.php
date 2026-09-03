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
            $table->double('reservation_balance', 10, 2)->default(0)->comment('flydubai air reservation balance')->after('pia_service_fee');
            $table->double('flydubai_bsp_fee', 10, 2)->default(0)->comment('if payment via bsp iata then added that amount')->after('reservation_balance');
            $table->double('issued_amount_tkt_time', 10, 2)->default(0)->comment('flydubai flight amount ticket confirmation time')->after('flydubai_bsp_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reservation_balance');
            $table->dropColumn('flydubai_bsp_fee');
            $table->dropColumn('issued_amount_tkt_time');
        });
    }
};
