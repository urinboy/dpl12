<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run()
    {
        // Test foydalanuvchini olish
        $user = User::where('email', 'customer@test.com')->first();
        
        if (!$user) {
            $this->command->warn('Test user not found.');
            return;
        }

        // Test reviewlari
        $reviews = [
            [
                'user_id' => $user->id,
                'product_id' => 1, // Olma
                'order_id' => 1,
                'rating' => 5,
                'comment' => 'Juda mazali va yangi olma. Oilam juda yoqtirdi. Qayta sotib olaman albatta!',
                'pros' => ['Mazali', 'Yangi', 'Sifatli'],
                'cons' => null,
                'is_approved' => true,
                'is_verified_purchase' => true
            ],
            [
                'user_id' => $user->id,
                'product_id' => 2, // Sabzi
                'order_id' => 1,
                'rating' => 4,
                'comment' => 'Yaxshi sabzi, lekin biroz qattiq edi. Umumiy holda mamnunman.',
                'pros' => ['Toza', 'Narxi mos'],
                'cons' => ['Biroz qattiq'],
                'is_approved' => true,
                'is_verified_purchase' => true
            ],
            [
                'user_id' => $user->id,
                'product_id' => 3, // Go'sht
                'order_id' => null,
                'rating' => 5,
                'comment' => 'Eng sifatli go\'sht! Juda yumshoq va mazali.',
                'pros' => ['Yumshoq', 'Mazali', 'Halol'],
                'cons' => null,
                'is_approved' => true,
                'is_verified_purchase' => false
            ]
        ];

        foreach ($reviews as $reviewData) {
            $review = Review::create($reviewData);
            
            // Mahsulot rating'ini yangilash
            $product = Product::find($review->product_id);
            if ($product) {
                $product->updateRatingStatistics();
            }
        }

        // $this->command->info('Test reviews created successfully!');
    }
}