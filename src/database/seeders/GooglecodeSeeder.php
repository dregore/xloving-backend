<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GooglecodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('googlecodes')->insert([
            [
                'account_id' => 1,
                'code' => '6633 4931',
            ],
            [
                'account_id' => 1,
                'code' => '1314 2522',
            ],
            [
                'account_id' => 1,
                'code' => '0403 6124',
            ],
            [
                'account_id' => 1,
                'code' => '1909 7760',
            ],
            [
                'account_id' => 1,
                'code' => '1814 6508',
            ],
            [
                'account_id' => 1,
                'code' => '6755 0473',
            ],
            [
                'account_id' => 1,
                'code' => '8433 5885',
            ],
            [
                'account_id' => 1,
                'code' => '7553 6864',
            ],
            [
                'account_id' => 1,
                'code' => '0691 8554',
            ],
            [
                'account_id' => 1,
                'code' => '6298 1425',
            ],
        ]);
    }
}
