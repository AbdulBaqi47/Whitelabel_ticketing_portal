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
        Schema::create('group_flights', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sector');
            $table->foreignId('airline_id')->constrained('airlines')->cascadeOnDelete();
            $table->unsignedInteger('group_type_id');
            $table->enum('status', ['pending', 'publish', 'not-publish'])->default('publish')->comment('pending, publish, not-publish');
            $table->double('purchased_price')->nullable();
            $table->double('adult_price')->nullable();
            $table->boolean('adult_price_call')->default(false);
            $table->double('cnn_price')->nullable();
            $table->boolean('cnn_price_call')->default(false);
            $table->double('inf_price')->nullable();
            $table->boolean('inf_price_call')->default(false);
            $table->string('baggage')->nullable();
            $table->string('meal')->nullable();
            $table->string('pnr')->nullable();
            $table->string('total_seats')->nullable();
            $table->text('rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_flights');
    }
};
