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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id');
            $table->boolean('can_issue_ticket')->default(\App\Models\UserSetting::ACTIVE);
            $table->boolean('can_flight_search')->default(\App\Models\UserSetting::ACTIVE);
            $table->double('void_charges')->nullable();
            $table->double('soto_void_charges')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
