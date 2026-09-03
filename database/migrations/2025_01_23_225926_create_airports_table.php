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
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ident')->nullable();
            $table->string('name')->index();
            $table->string('coordinates')->nullable();
            $table->string('continent')->nullable();
            $table->string('country')->nullable();
            $table->string('iso_country')->nullable();
            $table->string('municipality')->nullable();
            $table->string('gps_code')->nullable();
            $table->string('iata_code')->nullable();
            $table->string('local_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
