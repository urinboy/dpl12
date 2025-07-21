<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $fillable = [
        'review_id',
        'image_path',
        'original_name',
        'file_size',
        'mime_type',
        'sort_order'
    ];

    /**
     * Review
     */
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Full image URL
     */
    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    /**
     * Thumbnail URL
     */
    public function getThumbnailUrlAttribute()
    {
        return asset('storage/thumbnails/' . $this->image_path);
    }
}
