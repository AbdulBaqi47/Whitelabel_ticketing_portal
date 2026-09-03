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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('booking_id')->unique();
            $table->enum('status', ['expired', 'confirmed', 'issued', 'voided', 'refunded', 'cancelled'])->default('confirmed');
            $table->double('base_fare');
            $table->string('type')->default('flight');
            $table->double('tax');
            $table->double('gross_fare');
            $table->double('discount');
            $table->double('total_amount');
            $table->string('phone_number')->comment('Contacting Person Phone Number');
            $table->string('booking_pnr', length: 6)->index('booking_reservation_primary_pnr_index');
            $table->string('pnr_meta')->nullable();
            $table->string('provider_name')->nullable();
            $table->dateTime('pnr_expiry')->nullable();
            $table->foreignId('airline_id')->unsigned()->index('airline_id_index');
            $table->string('departure_airport');
            $table->dateTime('departure_date_time', 0);
            $table->string('arrival_airport');
            $table->dateTime('arrival_date_time', 0);
            $table->string('r_departure_airport')->nullable();
            $table->dateTime('r_departure_date_time', 0)->nullable();
            $table->string('r_arrival_airport')->nullable();
            $table->dateTime('r_arrival_date_time', 0)->nullable();
            $table->json('baggage')->nullable();
            $table->string('booking_brand')->nullable();
            $table->foreignId('applied_discount')->unsigned();
            $table->json('fare_break_down')->nullable();
            $table->string('booking_class')->nullable();
            $table->boolean('is_refundable')->default(1)->comment('Refundable ticket');
            $table->longText('refund_penalties')->nullable();
            $table->longText('exchange_penalties')->nullable();
            $table->boolean('is_multi_city')->default(0);
            $table->string('issuing_pcc', length: 11)->nullable();
            $table->string('reserving_pcc', length: 11)->nullable();
            $table->string('tour_code', length: 11)->nullable();
            $table->foreignId('supplier_id')->unsigned()->index('supplier_id_index');
            $table->foreignId('booked_by')->unsigned()->index('reservation_booking_by_user_id_index');
            $table->foreignId('issued_by')->nullable();
            $table->foreignId('request_by')->nullable();
            $table->foreignId('org_id')->nullable();
            $table->json('fare_rules')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
