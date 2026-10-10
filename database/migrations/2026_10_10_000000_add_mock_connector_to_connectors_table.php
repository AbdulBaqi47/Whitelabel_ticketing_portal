<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use App\Models\Connector;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Connector::firstOrCreate(
            ['type' => 'MOCK'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mock Flights (Demo)',
                'api_key' => 'mock_key',
                'api_secret' => 'mock_secret',
                'is_enable' => false,
                'supplier_id' => 1,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Connector::where('type', 'MOCK')->delete();
    }
};

