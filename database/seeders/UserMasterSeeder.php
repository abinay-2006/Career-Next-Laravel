<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserMasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user_master')->updateOrInsert(
            ['UserName' => 'admin'],
            [
                'Password' => Hash::make('admin'),
            ]
        );
    }
}
