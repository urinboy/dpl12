<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name', 
        'native_name',
        'is_active',
        'is_default',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean'
    ];

    /**
     * Default til olish
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Aktiv tillar
     */
    public static function getActive()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}