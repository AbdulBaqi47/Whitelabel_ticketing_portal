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
        Schema::create('transaction_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unsigned()->index('transaction_id_index');
            $table->date('adjustment_date')->nullable();
            $table->longText('entry_detail')->nullable();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->double('debit');
            $table->double('credit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_entries');
    }
};
