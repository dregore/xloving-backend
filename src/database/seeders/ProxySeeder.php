<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProxySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('proxies')->insert([
            [
                'type' => 'socks5',
                'server' => '116.203.9.26',
                'port' => 20502,
                'login' => 'booblik',
                'password' => 'b00blik4',
                'country' => 'gb',
            ],
            [
                'type' => 'socks5',
                'server' => '116.202.177.190',
                'port' => 20508,
                'login' => 'booblik',
                'password' => 'b00blik4',
                'country' => 'gb',
            ],
            [
                'type' => 'socks5',
                'server' => '217.182.175.114',
                'port' => 10106,
                'login' => 'Wnzf9',
                'password' => 'ixb6v',
                'country' => 'us',
            ],
            [
                'type' => 'socks5',
                'server' => '217.182.175.114',
                'port' => 10271,
                'login' => 'Kk1bn',
                'password' => 'zBz5X',
                'country' => 'us',
            ],
            [
                'type' => 'socks5',
                'server' => 'srv1.ipkings.io',
                'port' => 58755,
                'login' => 'zjtp1f9gn8',
                'password' => 'kwphnpg80d',
                'country' => 'us',
            ],
            [
                'type' => 'socks5',
                'server' => 'srv1.ipkings.io',
                'port' => 55774,
                'login' => 'olgdw0xpya',
                'password' => 'p3cmua8zd6',
                'country' => 'us',
            ],
            [
                'type' => 'socks5',
                'server' => 'srv1.ipkings.io',
                'port' => 58339,
                'login' => 'd04xlanwsk',
                'password' => 'asgjty6zec',
                'country' => 'us',
            ],
            [
                'type' => 'socks5',
                'server' => 'srv2.ipkings.io',
                'port' => 58487,
                'login' => 'ugfnma48wx',
                'password' => 'a7g2b34kw1',
                'country' => 'de',
            ],
        ]);
    }
}
