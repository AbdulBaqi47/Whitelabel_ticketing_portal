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
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unsigned()->index('idx_login_logs_user');
            $table->foreignId('org_id')->unsigned()->index('idx_login_logs_user_org');
            $table->boolean('status')->default(0);
            $table->timestamp('login_at')->index('idx_login_logs_date');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('client_data')->nullable();
            $table->string('device_browser')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
