<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiktokSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('accounts')->insert([
            [
                'type' => 'tiktok',
                'email' => 'xxidkk@blondmail.com',
                'password' => 'Sara123@',
                'country' => 'us',
            ],
            [
                'type' => 'tiktok',
                'email' => '0467pv4@mailaugust.com',
                'password' => 'dodo@2024',
                'country' => 'us',
            ],
            [
                'type' => 'tiktok',
                'email' => 'saar@blondmail.com',
                'password' => 'Sara123@',
                'country' => 'us',
            ],
            [
                'type' => 'tiktok',
                'email' => 'g00mzmq@dispmail.org',
                'password' => 'dodo@2024',
                'country' => 'us',
            ],
            [
                'type' => 'tiktok',
                'email' => 'ahmed677@blondmail.com',
                'password' => 'Sara123@',
                'country' => 'us',
            ],
        ]);
    }
}
