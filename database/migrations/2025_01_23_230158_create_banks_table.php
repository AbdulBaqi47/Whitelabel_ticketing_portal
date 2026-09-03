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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('account_number')->unique();
            $table->string('account_holder_name');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->boolean('status')->default(\App\Models\Bank::ACTIVE);
            $table->string('bank_logo')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('contact_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
