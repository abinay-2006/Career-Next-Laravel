<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('employer_reg')->updateOrInsert(
            ['UserName' => 'abc@gmail.com'],
            [
                'CompanyName'  => 'ABC Technologies',
                'ContactPerson' => 'ABC Admin',
                'Address'      => 'Kochi, Kerala',
                'City'         => 'Kochi',
                'Email'        => 'abc@gmail.com',
                'Mobile'       => '9876543210',
                'Area_Work'    => 'Information Technology',
                'Status'       => 'Confirm',
                'Password'     => Hash::make('123456'),
                'Question'     => 'What is your company name?',
                'Answer'       => 'ABC Technologies',
                'Photo'        => null,
            ]
        );
    }
}
