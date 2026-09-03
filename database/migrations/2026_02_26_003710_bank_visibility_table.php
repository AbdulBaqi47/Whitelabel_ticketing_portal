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
        
         Schema::create('bank_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id');
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->unique(['bank_id', 'org_id']);
         });   
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_visibility');
    }
};
