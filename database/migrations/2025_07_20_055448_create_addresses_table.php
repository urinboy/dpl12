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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title')->default('Home'); // "Uy", "Ish", "Boshqa"
            $table->text('address'); // To'liq manzil
            $table->foreignId('city_id')->constrained('cities');
            $table->string('district')->nullable(); // Tuman/mahalla
            $table->string('landmark')->nullable(); // Mo'ljal
            $table->string('phone')->nullable(); // Qo'shimcha telefon
            $table->decimal('latitude', 10, 8)->nullable(); // Koordinatalar
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false); // Asosiy manzil
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
