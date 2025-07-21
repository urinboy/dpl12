<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ORD-2024-001234
            $table->foreignId('user_id')->constrained('users');
            
            // Order status
            $table->enum('status', [
                'pending',      // Kutilmoqda
                'confirmed',    // Tasdiqlangan
                'processing',   // Tayyorlanmoqda
                'shipped',      // Yuborilgan
                'delivered',    // Yetkazilgan
                'cancelled'     // Bekor qilingan
            ])->default('pending');
            
            // Amounts
            $table->decimal('subtotal', 10, 2); // Mahsulotlar yig'indisi
            $table->decimal('delivery_fee', 10, 2)->default(0); // Yetkazib berish to'lovi
            $table->decimal('discount_amount', 10, 2)->default(0); // Chegirma
            $table->decimal('total_amount', 10, 2); // Umumiy summa
            
            // Payment
            $table->enum('payment_method', ['cash', 'card', 'online'])->default('cash');
            $table->enum('payment_status', [
                'pending',   // To'lanmagan
                'paid',      // To'langan
                'failed',    // Xatolik
                'refunded'   // Qaytarilgan
            ])->default('pending');
            
            // Delivery information
            $table->json('delivery_address'); // Yetkazib berish manzili
            $table->foreignId('delivery_city_id')->constrained('cities');
            $table->date('delivery_date')->nullable(); // Yetkazib berish sanasi
            $table->string('delivery_time_slot')->nullable(); // Vaqt oralig'i
            
            // Additional info
            $table->text('notes')->nullable(); // Izohlar
            $table->text('cancellation_reason')->nullable(); // Bekor qilish sababi
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
