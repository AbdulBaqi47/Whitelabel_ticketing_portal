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
        Schema::table('connectors', function (Blueprint $table) {
            if (!Schema::hasColumn('connectors', 'environment')) {
                $table->string('environment', 10)->default('UAT')->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            if (Schema::hasColumn('connectors', 'environment')) {
                $table->dropColumn('environment');
            }
        });
    }
};

