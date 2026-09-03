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
            $table->string('pia_service_fee')->nullable()->after('total_amount')->comment('Pakistan International Airlines Service Fee');
            $table->boolean('branch_comm_applied')->after('applied_discount_percent')->default(0)->comment('Branch Commission Applied');
            $table->string('branch_comm_value')->nullable()->after('branch_comm_applied')->comment('Branch Commission Type');
            $table->string('branch_comm_amount')->nullable()->after('branch_comm_value')->comment('Branch Commission Value');
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
