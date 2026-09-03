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
        Schema::create('registeration_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('agency_name');
            $table->string('name');
            $table->string('email');
            $table->string('currency')->default('PKR');
            $table->string('country')->default('pakistan');
            $table->string('city');
            $table->longText('address')->nullable();
            $table->string('phone_number');
            $table->boolean('mark_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registeration_requests');
    }
};
