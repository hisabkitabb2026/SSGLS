<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('countries')->delete();
        $countries = [
            ['id' => 101, 'code' => 'IN', 'name' => 'India', 'phonecode' => 91],
            ['id' => 231, 'code' => 'US', 'name' => 'United States', 'phonecode' => 1],
            ['id' => 2, 'code' => 'DE', 'name' => 'Germany', 'phonecode' => 49],
            ['id' => 3, 'code' => 'FR', 'name' => 'France', 'phonecode' => 33],
        ];
        DB::table('countries')->insert($countries);
    }
}
