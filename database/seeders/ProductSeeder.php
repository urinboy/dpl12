<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\City;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
//         // Test shahar yaratish
//         $city = City::create([
//             'is_active' => true,
//             'delivery_available' => true,
//             'delivery_fee' => 5000
//         ]);

//         // Shahar tarjimasini qo'shish
//         $city->setTranslation('name', 'uz', 'Toshkent');
//         $city->setTranslation('name', 'ru', 'Ташкент');

        // Test seller yaratish
        $seller = User::create([
            'name' => 'Test Seller',
            'email' => 'seller@test.com',
            'phone' => '+998901234567',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'city_id' => 1,
            'is_active' => true
        ]);

        // Test customer yaratish
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '+998901234568',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'city_id' => 1,
            'is_active' => true
        ]);

        // Test mahsulotlar
        $products = [
            [
                'seller_id' => $seller->id,
                'category_id' => 3, // Meva va sabzavotlar
                'price' => 15000,
                'discount_price' => 12000,
                'unit' => 'kg',
                'stock_quantity' => 100,
                'min_order_quantity' => 1,
                'sku' => 'APPLE001',
                'weight' => 1.0,
                'is_featured' => true,
                'is_active' => true,
                'translations' => [
                    'uz' => [
                        'name' => 'Qizil olma',
                        'description' => 'Yangi va mazali qizil olma. Organik usulda yetishtrilgan. Vitamin C va tolalar bilan boy.'
                    ],
                    'ru' => [
                        'name' => 'Красное яблоко',
                        'description' => 'Свежее и вкусное красное яблоко. Выращено органическим способом. Богато витамином C и клетчаткой.'
                    ]
                ]
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => 3,
                'price' => 8000,
                'unit' => 'kg',
                'stock_quantity' => 50,
                'min_order_quantity' => 1,
                'sku' => 'CARROT001',
                'weight' => 1.0,
                'is_featured' => false,
                'is_active' => true,
                'translations' => [
                    'uz' => [
                        'name' => 'Sabzi',
                        'description' => 'Yangi va shirinroq sabzi. A vitamini bilan boy. Salat va palov uchun ajoyib.'
                    ],
                    'ru' => [
                        'name' => 'Морковь',
                        'description' => 'Свежая и сладкая морковь. Богата витамином А. Отлично для салатов и плова.'
                    ]
                ]
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => 4, // Go'sht va baliq
                'price' => 85000,
                'discount_price' => 80000,
                'unit' => 'kg',
                'stock_quantity' => 20,
                'min_order_quantity' => 1,
                'sku' => 'BEEF001',
                'weight' => 1.0,
                'is_featured' => true,
                'is_active' => true,
                'translations' => [
                    'uz' => [
                        'name' => 'Mol go\'shti',
                        'description' => 'Yangi mol go\'shti. Halol va sifatli mahsulot. Oqsil va temir bilan boy.'
                    ],
                    'ru' => [
                        'name' => 'Говядина',
                        'description' => 'Свежая говядина. Халяльный и качественный продукт. Богата белком и железом.'
                    ]
                ]
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => 3,
                'price' => 6000,
                'unit' => 'kg',
                'stock_quantity' => 75,
                'min_order_quantity' => 1,
                'sku' => 'POTATO001',
                'weight' => 1.0,
                'is_featured' => false,
                'is_active' => true,
                'translations' => [
                    'uz' => [
                        'name' => 'Kartoshka',
                        'description' => 'Sifatli kartoshka. Kraxmal bilan boy. Qovurish va qaynatish uchun mos.'
                    ],
                    'ru' => [
                        'name' => 'Картофель',
                        'description' => 'Качественный картофель. Богат крахмалом. Подходит для жарки и варки.'
                    ]
                ]
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => 2, // Ichimliklar
                'price' => 3500,
                'unit' => 'litr',
                'stock_quantity' => 200,
                'min_order_quantity' => 1,
                'sku' => 'MILK001',
                'weight' => 1.0,
                'is_featured' => true,
                'is_active' => true,
                'translations' => [
                    'uz' => [
                        'name' => 'Sut',
                        'description' => 'Yangi sigir suti. Kaltsiy va oqsil bilan boy. Pasteurizatsiya qilingan.'
                    ],
                    'ru' => [
                        'name' => 'Молоко',
                        'description' => 'Свежее коровье молоко. Богато кальцием и белком. Пастеризованное.'
                    ]
                ]
            ]
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'seller_id' => $productData['seller_id'],
                'category_id' => $productData['category_id'],
                'price' => $productData['price'],
                'discount_price' => $productData['discount_price'] ?? null,
                'unit' => $productData['unit'],
                'stock_quantity' => $productData['stock_quantity'],
                'min_order_quantity' => $productData['min_order_quantity'],
                'sku' => $productData['sku'],
                'weight' => $productData['weight'],
                'is_featured' => $productData['is_featured'],
                'is_active' => $productData['is_active']
            ]);

            // Tarjimalarni saqlash
            foreach ($productData['translations'] as $locale => $translations) {
                foreach ($translations as $field => $value) {
                    $product->setTranslation($field, $locale, $value);
                }
            }
        }

        // $this->command->info('Products created successfully!');
    }
}