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
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->index();
            $table->string('thumbnail')->nullable();
            $table->string('iata_desi')->nullable();
            $table->string('icao_code')->nullable();
            $table->string('iata_code')->nullable();
            $table->boolean('status')->default(\App\Models\Airline::ACTIVE);
            $table->string('issuing_pcc')->nullable();
            $table->string('reserving_pcc')->nullable();
            $table->string('tour_code')->nullable();
            $table->foreignId('country_id')->unsigned()->index('country_id_index');
            $table->string('preferred_connector')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
