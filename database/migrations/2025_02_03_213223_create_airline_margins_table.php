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
        Schema::create('airline_margins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sales_channel');
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->string('region')->nullable();
            $table->double('margin')->default(0);
            $table->enum('margin_type', ['amount', 'percentage'])->default('percentage');
            $table->date('sale_start_continue')->nullable();
            $table->date('sale_end_continue')->nullable();
            $table->date('travel_start_continue')->nullable();
            $table->date('travel_end_continue')->nullable();
            $table->string('rbds')->nullable();
            $table->boolean('is_apply_on_gross');
            $table->boolean('status')->default(1);
            $table->longText('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airline_margins');
    }
};
