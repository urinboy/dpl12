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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Rating (1-5 yulduz)
            $table->integer('rating')->unsigned()->default(5);
            
            // Review content
            $table->text('comment')->nullable();
            $table->json('pros')->nullable(); // Ijobiy tomonlar array
            $table->json('cons')->nullable(); // Salbiy tomonlar array
            
            // Moderation
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_verified_purchase')->default(false); // Sotib olgan mijozmi?
            $table->text('admin_comment')->nullable(); // Admin izohi
            
            // Helpfulness
            $table->integer('helpful_count')->default(0); // Foydali deb belgilanganlar
            $table->integer('not_helpful_count')->default(0); // Foydali emas deb belgilanganlar
            
            // Dates
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Indexlar
            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id', 'product_id']); // Bir foydalanuvchi bir mahsulotga bir marta sharh
            $table->index(['rating', 'is_approved']);
            $table->index('is_verified_purchase');

            // Constraint: bir foydalanuvchi bir mahsulotga faqat bir sharh
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
