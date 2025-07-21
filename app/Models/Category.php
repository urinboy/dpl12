<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use Translatable;

    protected $fillable = [
        'parent_id',
        'image',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Tarjima qilinadigan fieldlar
    protected $translatable = ['name', 'description'];

    /**
     * Parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Products
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'children_count' => $this->children()->count(),
            'products_count' => $this->products()->where('is_active', true)->count()
        ];
    }
}