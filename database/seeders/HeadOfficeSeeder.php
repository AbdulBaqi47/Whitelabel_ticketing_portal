<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HeadOfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $date_time = \Carbon\Carbon::now();
        $head_office = [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Mati ur Rehman',
            'email' => 'superadmin@gmail.com',
            'email_verified_at' => $date_time,
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            'phone_number' => '03119468498',
            'status' => \App\Models\User::$status['active'],
            'created_at' => $date_time,
            'updated_at' => $date_time
        ];

        $user = \App\Models\User::create($head_office);
        $role = \App\Models\Role::where(['name'=> \App\Models\Role::HEADOFFICE])->firstOrFail();
        $permissions = \App\Models\Permission::whereJsonContains('preference', \App\Models\Role::HEADOFFICE)->pluck('uuid')->toArray();
        $user->assignRole($role);
        $user->syncPermissions($permissions);
        dump('created Successfully');
    }
}
