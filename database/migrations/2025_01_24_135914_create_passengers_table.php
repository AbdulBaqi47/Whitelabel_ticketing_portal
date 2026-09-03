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
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->enum('title', \App\Models\Passenger::$title)->default(\App\Models\Passenger::$title[0]);
            $table->enum('passenger_type', \App\Models\Passenger::$passenger_type)->default( \App\Models\Passenger::$passenger_type[0]);
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('d_type', ['P', 'I'])->default('P');
            $table->string('d_number');
            $table->date('d_expiry');
            $table->date('date_of_birth')->nullable();
            $table->string('ticket_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};
