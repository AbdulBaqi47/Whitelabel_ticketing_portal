<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AgentAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Account::insert([
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'COMPANY_REVENUE',
                'code' => Str::upper(Str::random(10)),
                'description' => 'COMPANY REVENUE ACCOUNT',
                'org_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'REFUND_PENALTY_CHARGE',
                'code' => Str::upper(Str::random(10)),
                'description' => 'REFUND PENALTY CHARGE ACCOUNT',
                'org_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'VOID_CHARGE_INCOME',
                'code' => Str::upper(Str::random(10)),
                'description' => 'VOID CHARGE INCOME ACCOUNT',
                'org_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $agents = \App\Models\Organization::whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'agency');
        })->get();

        foreach ($agents as $agent) {
            \App\Models\Account::create([
                'name' => 'AGENCY_WALLET',
                'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                'description' => 'AGENCY WALLET ACCOUNT',
                'org_id' => $agent->id,
            ]);

            \App\Models\Account::create([
                'name' => 'AGENT_TEMP_LIMIT',
                'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                'description' => 'AGENCY TEMPORARY LIMIT ACCOUNT',
                'org_id' => $agent->id,
            ]);

        }

        dump('DONE');
    }
}
