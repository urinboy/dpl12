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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type'); // Model nomi: App\Models\Category, App\Models\Product
            $table->unsignedBigInteger('translatable_id'); // Model ID si
            $table->string('language_code', 2); // uz, ru, en
            $table->string('field'); // name, description, title
            $table->text('value'); // tarjima matni
            $table->timestamps();

            // Composite index - tez qidiruv uchun
            $table->index(['translatable_type', 'translatable_id', 'language_code']);
            $table->index(['translatable_type', 'field', 'language_code']);
            
            // Foreign key
            $table->foreign('language_code')->references('code')->on('languages');
            
            // Bir xil field uchun bir xil til bo'lmasligi kerak
            $table->unique(['translatable_type', 'translatable_id', 'field', 'language_code'], 'unique_translation');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
