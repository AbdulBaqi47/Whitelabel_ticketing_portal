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
        Schema::create('group_flight_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_flight_id')->nullable();
            $table->string('flight_number')->nullable();
            $table->date('departure_date')->nullable();
            $table->time('departure_time')->nullable();
            $table->string('departure_city')->nullable();

            $table->date('arrival_date')->nullable();
            $table->time('arrival_time')->nullable();
            $table->string('arrival_city')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_flight_flights');
    }
};
