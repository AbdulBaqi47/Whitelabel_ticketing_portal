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
        Schema::create('booked_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('flight_pnr')->nullable();
            $table->string('o_flight_number')->index()->nullable();
            $table->string('o_airline')->comment('Origin Airline');
            $table->string('m_flight_number')->nullable()->comment('Marketing Airline Flight Number');
            $table->string('m_airline')->nullable()->comment('Marketing Airline'); 
            $table->string('seg_origin')->comment('Segment Origin');
            $table->string('seg_destination')->comment('Segment Destination');
            $table->dateTime('seg_departure_datetime', 0);
            $table->dateTime('seg_arrival_datetime', 0);
            $table->string('departure_terminal')->nullable();
            $table->string('arrival_terminal')->nullable();
            $table->string('cabin_fullname')->nullable();
            $table->string('flight_status_code')->nullable();
            $table->enum('flight_type', ['outbound', 'inbound'])->default('outbound');
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('flight_duration');
            $table->json('meal')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booked_segments');
    }
};
