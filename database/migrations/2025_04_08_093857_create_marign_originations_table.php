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
        Schema::create('marign_originations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id');
            $table->foreignId('parent_org_id')->nullable();
            $table->foreignId('airline_margin_id');
            $table->double('margin')->default(0);
            $table->enum('margin_type', ['amount', 'percentage'])->default('percentage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marign_originations');
    }
};
