<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run()
    {
        // Test foydalanuvchini olish
        $user = User::where('email', 'customer@test.com')->first();
        $address = Address::where('user_id', $user->id)->where('is_default', true)->first();
        
        if (!$user || !$address) {
            $this->command->warn('Test user or address not found. Please run previous seeders first.');
            return;
        }

        // Test buyurtmalari
        $orders = [
            [
                'order_number' => 'ORD-2024-000001',
                'user_id' => $user->id,
                'status' => 'delivered',
                'subtotal' => 27000,
                'delivery_fee' => 5000,
                'discount_amount' => 0,
                'total_amount' => 32000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'delivery_address' => [
                    'title' => $address->title,
                    'address' => $address->address,
                    'district' => $address->district,
                    'landmark' => $address->landmark,
                    'phone' => $address->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude
                ],
                'delivery_city_id' => $address->city_id,
                'delivery_date' => now()->subDays(3)->format('Y-m-d'),
                'delivery_time_slot' => '10:00-12:00',
                'notes' => 'Tez yetkazib bering',
                'confirmed_at' => now()->subDays(5),
                'shipped_at' => now()->subDays(4),
                'delivered_at' => now()->subDays(3),
                'created_at' => now()->subDays(5),
                'items' => [
                    ['product_id' => 1, 'quantity' => 2], // Olma
                    ['product_id' => 2, 'quantity' => 1]  // Sabzi
                ]
            ],
            [
                'order_number' => 'ORD-2024-000002',
                'user_id' => $user->id,
                'status' => 'confirmed',
                'subtotal' => 24000,
                'delivery_fee' => 5000,
                'discount_amount' => 0,
                'total_amount' => 29000,
                'payment_method' => 'card',
                'payment_status' => 'pending',
                'delivery_address' => [
                    'title' => $address->title,
                    'address' => $address->address,
                    'district' => $address->district,
                    'landmark' => $address->landmark,
                    'phone' => $address->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude
                ],
                'delivery_city_id' => $address->city_id,
                'delivery_date' => now()->addDay()->format('Y-m-d'),
                'delivery_time_slot' => '14:00-16:00',
                'notes' => null,
                'confirmed_at' => now()->subHours(2),
                'created_at' => now()->subHours(3),
                'items' => [
                    ['product_id' => 1, 'quantity' => 2] // Olma
                ]
            ],
            [
                'order_number' => 'ORD-2024-000003',
                'user_id' => $user->id,
                'status' => 'cancelled',
                'subtotal' => 15000,
                'delivery_fee' => 5000,
                'discount_amount' => 0,
                'total_amount' => 20000,
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'delivery_address' => [
                    'title' => $address->title,
                    'address' => $address->address,
                    'district' => $address->district,
                    'landmark' => $address->landmark,
                    'phone' => $address->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude
                ],
                'delivery_city_id' => $address->city_id,
                'delivery_date' => now()->format('Y-m-d'),
                'delivery_time_slot' => '16:00-18:00',
                'notes' => null,
                'cancellation_reason' => 'Foydalanuvchi o\'zi bekor qildi',
                'cancelled_at' => now()->subMinutes(30),
                'created_at' => now()->subHour(),
                'items' => [
                    ['product_id' => 4, 'quantity' => 2] // Kartoshka
                ]
            ]
        ];

        foreach ($orders as $orderData) {
            $items = $orderData['items'];
            unset($orderData['items']);

            $order = Order::create($orderData);

            // Order items yaratish
            foreach ($items as $itemData) {
                $product = Product::find($itemData['product_id']);
                
                if ($product) {
                    $currentPrice = $product->discount_price && $product->discount_price < $product->price 
                                   ? $product->discount_price 
                                   : $product->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->getTranslation('name', 'uz'),
                        'product_sku' => $product->sku,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $currentPrice,
                        'total_price' => $currentPrice * $itemData['quantity']
                    ]);
                }
            }
        }

        // $this->command->info('Test orders created successfully!');
    }
}