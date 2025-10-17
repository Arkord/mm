<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Administrador',
            'email' => null,
            'email_verified_at' => null,
            'password' => Hash::make('1'),
            'remember_token' => null,
            'created_at' => Carbon::parse('2025-09-15 05:35:31'),
            'updated_at' => Carbon::parse('2025-09-15 05:35:31'),
            'username' => 'admin',
            'role' => 'admin',
            'company_id' => null,
        ]);
    }
}
