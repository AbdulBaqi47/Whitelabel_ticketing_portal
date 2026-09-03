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
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->index();
            $table->string('type');
            $table->string('api_key');
            $table->string('api_secret');
            $table->string('connector_domain')->nullable();
            $table->string('pcc')->nullable();
            $table->string('printer')->nullable();
            $table->string('iata')->nullable();
            $table->string('client_ip')->nullable();
            $table->boolean('is_enable')->default(1);
            $table->foreignId('supplier_id')->unsigned()->index('supplier_id_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
