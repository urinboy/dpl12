<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run()
    {
        $cities = [
            [
                'id' => 1,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 5000,
                'translations' => [
                    'uz' => ['name' => 'Toshkent'],
                    'ru' => ['name' => 'Ташкент']
                ]
            ],
            [
                'id' => 2,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 7000,
                'translations' => [
                    'uz' => ['name' => 'Samarqand'],
                    'ru' => ['name' => 'Самарканд']
                ]
            ],
            [
                'id' => 3,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 8000,
                'translations' => [
                    'uz' => ['name' => 'Buxoro'],
                    'ru' => ['name' => 'Бухара']
                ]
            ],
            [
                'id' => 4,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 6000,
                'translations' => [
                    'uz' => ['name' => 'Andijon'],
                    'ru' => ['name' => 'Андижан']
                ]
            ],
            [
                'id' => 5,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 6500,
                'translations' => [
                    'uz' => ['name' => 'Namangan'],
                    'ru' => ['name' => 'Наманган']
                ]
            ],
            [
                'id' => 6,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 7500,
                'translations' => [
                    'uz' => ['name' => 'Farg\'ona'],
                    'ru' => ['name' => 'Фергана']
                ]
            ],
            [
                'id' => 7,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 9000,
                'translations' => [
                    'uz' => ['name' => 'Nukus'],
                    'ru' => ['name' => 'Нукус']
                ]
            ],
            [
                'id' => 8,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 8500,
                'translations' => [
                    'uz' => ['name' => 'Urganch'],
                    'ru' => ['name' => 'Ургенч']
                ]
            ],
            [
                'id' => 9,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 7000,
                'translations' => [
                    'uz' => ['name' => 'Qarshi'],
                    'ru' => ['name' => 'Карши']
                ]
            ],
            [
                'id' => 10,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 8000,
                'translations' => [
                    'uz' => ['name' => 'Termiz'],
                    'ru' => ['name' => 'Термез']
                ]
            ],
            [
                'id' => 11,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 6500,
                'translations' => [
                    'uz' => ['name' => 'Jizzax'],
                    'ru' => ['name' => 'Джизак']
                ]
            ],
            [
                'id' => 12,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 6000,
                'translations' => [
                    'uz' => ['name' => 'Guliston'],
                    'ru' => ['name' => 'Гулистан']
                ]
            ],
            [
                'id' => 13,
                'is_active' => true,
                'delivery_available' => false, // Yetkazib berish yo'q
                'delivery_fee' => 0,
                'translations' => [
                    'uz' => ['name' => 'Zarafshon'],
                    'ru' => ['name' => 'Зарафшан']
                ]
            ],
            [
                'id' => 14,
                'is_active' => true,
                'delivery_available' => true,
                'delivery_fee' => 10000,
                'translations' => [
                    'uz' => ['name' => 'Muynoq'],
                    'ru' => ['name' => 'Муйнак']
                ]
            ]
        ];

        foreach ($cities as $cityData) {
            $city = City::updateOrCreate(
                ['id' => $cityData['id']],
                [
                    'is_active' => $cityData['is_active'],
                    'delivery_available' => $cityData['delivery_available'],
                    'delivery_fee' => $cityData['delivery_fee']
                ]
            );

            // Tarjimalarni saqlash
            foreach ($cityData['translations'] as $locale => $translations) {
                foreach ($translations as $field => $value) {
                    $city->setTranslation($field, $locale, $value);
                }
            }
        }

        // $this->command->info('Cities created successfully!');
    }
}