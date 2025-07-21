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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('category_id')->constrained('categories');
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->enum('unit', ['kg', 'dona', 'litr', 'paket', 'metr'])->default('dona');
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_order_quantity')->default(1);
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->decimal('weight', 8, 3)->nullable(); // kg da
            $table->json('dimensions')->nullable(); // {length, width, height} cm da
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('views_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['seller_id', 'is_active']);
            $table->index(['is_featured', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
