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
        Schema::create('temporay_credit_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->unsigned()->index();
            $table->double('limit_amount');
            $table->double('used_amount');
            // $table->double('remaining_amount');
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->unsigned()->index();
            $table->boolean('instant_stop_limit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporay_credit_limits');
    }
};
