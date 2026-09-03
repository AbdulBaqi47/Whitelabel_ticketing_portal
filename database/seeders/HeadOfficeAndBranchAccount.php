<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HeadOfficeAndBranchAccount extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       \App\Models\Account::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HEAD_OFFICE_SALE',
            'code' => Str::upper(Str::random(10)),
            'description' => 'HEAD OFFICE SALE ACCOUNT',
            'org_id' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);


        $branches = \App\Models\Organization::whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'branch');
        })->get();

        foreach ($branches as $branch) {
            \App\Models\Account::create([
                'name' => 'BRANCH_SALE_ACCOUNT',
                'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                'description' => 'BRANCH SALES ACCOUNT',
                'org_id' => $branch->id,
            ]);
        }
    }
}
