<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JobSeekerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('jobseeker_reg')->updateOrInsert(
            ['UserName' => 'thomas@gmail.com'],
            [
                'JobSeekerName' => 'Thomas',
                'Address'       => 'Kochi, Kerala',
                'City'          => 'Kochi',
                'Email'         => 'thomas@gmail.com',
                'Mobile'        => 9876543211,
                'Qualification' => 'BCA',
                'Gender'        => 'Male',
                'BirthDate'     => '2000-01-01',
                'Resume'        => null,
                'Status'        => 'Confirm',
                'Password'      => Hash::make('123456'),
                'Question'      => 'What is your qualification?',
                'Answer'        => 'BCA',
                'Photo'         => null,
            ]
        );
    }
}
