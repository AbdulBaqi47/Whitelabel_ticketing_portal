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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique()->index();
            $table->longText('address')->nullable();
            $table->string('city', length:100)->nullable();
            $table->string('state', length:100)->nullable();
            $table->string('country', length:100)->nullable();
            $table->string('postal_code', length:20)->nullable();
            $table->string('phone_number', length:20)->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Branch Admin');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
