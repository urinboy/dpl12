<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'address',
        'city_id',
        'district',
        'landmark',
        'phone',
        'latitude',
        'longitude',
        'is_default'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean'
    ];

    /**
     * Foydalanuvchi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shahar
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'address' => $this->address,
            'district' => $this->district,
            'landmark' => $this->landmark,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->is_default,
            'city' => $this->city ? $this->city->toApiArray($locale) : null
        ];
    }

    /**
     * Default manzilni o'rnatish
     */
    public function setAsDefault()
    {
        // Oldingi default'ni o'chirish
        static::where('user_id', $this->user_id)
              ->where('id', '!=', $this->id)
              ->update(['is_default' => false]);
        
        // Bu manzilni default qilish
        $this->update(['is_default' => true]);
    }
}
