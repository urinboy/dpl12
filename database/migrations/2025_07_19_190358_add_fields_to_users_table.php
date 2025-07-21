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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->nullable()->after('email');
            $table->string('avatar')->nullable()->after('password');
            $table->text('address')->nullable()->after('avatar');
            $table->foreignId('city_id')->nullable()->constrained('cities')->after('address');
            $table->enum('role', ['customer', 'seller', 'admin'])->default('customer')->after('city_id');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn([
                'phone', 'avatar', 'address', 'city_id', 
                'role', 'is_active', 'phone_verified_at'
            ]);
        });
    }
};
