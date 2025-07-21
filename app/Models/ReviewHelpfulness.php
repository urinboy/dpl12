<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewHelpfulness extends Model
{
    protected $table = 'review_helpfulness';

    protected $fillable = [
        'review_id',
        'user_id',
        'is_helpful'
    ];

    protected $casts = [
        'is_helpful' => 'boolean'
    ];

    /**
     * Review
     */
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Foydalanuvchi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}