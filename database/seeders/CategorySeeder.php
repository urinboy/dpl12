<?php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // Asosiy kategoriyalar
        $categories = [
            [
                'id' => 1,
                'parent_id' => null,
                'sort_order' => 1,
                'translations' => [
                    'uz' => ['name' => 'Oziq-ovqat mahsulotlari', 'description' => 'Turli xil oziq-ovqat mahsulotlari'],
                    'ru' => ['name' => 'Продукты питания', 'description' => 'Различные продукты питания']
                ]
            ],
            [
                'id' => 2,
                'parent_id' => null,
                'sort_order' => 2,
                'translations' => [
                    'uz' => ['name' => 'Ichimliklar', 'description' => 'Turli xil ichimliklar'],
                    'ru' => ['name' => 'Напитки', 'description' => 'Различные напитки']
                ]
            ],
            [
                'id' => 3,
                'parent_id' => 1,
                'sort_order' => 1,
                'translations' => [
                    'uz' => ['name' => 'Meva va sabzavotlar', 'description' => 'Yangi meva va sabzavotlar'],
                    'ru' => ['name' => 'Фрукты и овощи', 'description' => 'Свежие фрукты и овощи']
                ]
            ],
            [
                'id' => 4,
                'parent_id' => 1,
                'sort_order' => 2,
                'translations' => [
                    'uz' => ['name' => 'Go\'sht va baliq', 'description' => 'Yangi go\'sht va baliq mahsulotlari'],
                    'ru' => ['name' => 'Мясо и рыба', 'description' => 'Свежие мясные и рыбные продукты']
                ]
            ]
        ];

        foreach ($categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['id' => $categoryData['id']],
                [
                    'parent_id' => $categoryData['parent_id'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true
                ]
            );

            // Tarjimalarni saqlash
            foreach ($categoryData['translations'] as $locale => $translations) {
                foreach ($translations as $field => $value) {
                    $category->setTranslation($field, $locale, $value);
                }
            }
        }
    }
}