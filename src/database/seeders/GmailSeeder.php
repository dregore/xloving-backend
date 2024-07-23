<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GmailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('accounts')->insert([
            [
                'type' => 'google',
                'email' => 'armentafkg@gmail.com',
                'password' => 'xFqNZZnwXa',
                'country' => 'us',
            ],
            [
                'type' => 'google',
                'email' => 'hokutanvlp@gmail.com',
                'password' => 'zgaOeqzx',
                'country' => 'us',
            ],
            [
                'type' => 'google',
                'email' => 'd89240313@gmail.com',
                'password' => 'rq8pdY7srK7',
                'country' => 'us',
            ],
            [
                'type' => 'google',
                'email' => 'hogueonu@gmail.com',
                'password' => '1UMFqvMn27u',
                'country' => 'us',
            ],
            [
                'type' => 'google',
                'email' => 'barrettbve@gmail.com',
                'password' => 'rGH4gjhrE8',
                'country' => 'us',
            ],
        ]);
    }
}
