<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrenciesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'name' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'precision' => '2',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
            [
                'name' => 'British Pound Sterling',
                'code' => 'GBP',
                'symbol' => '£',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Japanese Yen',
                'code' => 'JPY',
                'symbol' => '¥',
                'precision' => '0',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Canadian Dollar',
                'code' => 'CAD',
                'symbol' => 'C$',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Australian Dollar',
                'code' => 'AUD',
                'symbol' => 'A$',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Swiss Franc',
                'code' => 'CHF',
                'symbol' => 'CHF',
                'precision' => '2',
                'thousand_separator' => "'",
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Chinese Yuan',
                'code' => 'CNY',
                'symbol' => '¥',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'name' => 'Brazilian Real',
                'code' => 'BRL',
                'symbol' => 'R$',
                'precision' => '2',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::create($currency);
        }
    }
}
