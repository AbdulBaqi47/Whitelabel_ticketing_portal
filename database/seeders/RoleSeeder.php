<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'head-office',
                'guard_name' => 'web',
                'display_name' => 'HEAD-OFFICE'
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'h-employee',
                'guard_name' => 'web',
                'display_name' => 'EMPLOYEE'
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'branch',
                'guard_name' => 'web',
                'display_name' => 'BRANCH'
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'b-employee',
                'guard_name' => 'web',
                'display_name' => 'EMPLOYEE'
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'agency',
                'guard_name' => 'web',
                'display_name' => 'AGENCY',
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'a-employee',
                'guard_name' => 'web',
                'display_name' => 'EMPLOYEE',
            ],
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'sub-agent',
                'guard_name' => 'web',
                'display_name' => 'SUB-AGENT',
            ]
        ];
        \Illuminate\Support\Facades\DB::table('roles')->insert($roles);
    }
}
