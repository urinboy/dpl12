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
        Schema::table('products', function (Blueprint $table) {
            // Rating statistikalari (mavjud fieldlarni yangilaymiz)
            $table->decimal('rating_1_star', 8, 2)->default(0)->after('rating_count');
            $table->decimal('rating_2_star', 8, 2)->default(0)->after('rating_1_star');
            $table->decimal('rating_3_star', 8, 2)->default(0)->after('rating_2_star');
            $table->decimal('rating_4_star', 8, 2)->default(0)->after('rating_3_star');
            $table->decimal('rating_5_star', 8, 2)->default(0)->after('rating_4_star');
            
            // Review statistikalari
            $table->integer('reviews_count')->default(0)->after('rating_5_star');
            $table->integer('verified_reviews_count')->default(0)->after('reviews_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'rating_1_star', 'rating_2_star', 'rating_3_star', 
                'rating_4_star', 'rating_5_star', 'reviews_count', 
                'verified_reviews_count'
            ]);
        });
    }
};
