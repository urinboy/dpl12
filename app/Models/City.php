<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use Translatable;

    protected $fillable = [
        'is_active',
        'delivery_available',
        'delivery_fee'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'delivery_available' => 'boolean',
        'delivery_fee' => 'decimal:2'
    ];

    // Tarjima qilinadigan fieldlar
    protected $translatable = ['name'];

    /**
     * Bu shahardagi foydalanuvchilar
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Bu shahardagi manzillar
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Aktiv shaharlar
     */
    public static function getActive()
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Yetkazib berish mavjud shaharlar
     */
    public static function getDeliveryAvailable()
    {
        return static::where('is_active', true)
                    ->where('delivery_available', true)
                    ->get();
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'is_active' => $this->is_active,
            'delivery_available' => $this->delivery_available,
            'delivery_fee' => $this->delivery_fee,
            'users_count' => $this->users()->count()
        ];
    }
}