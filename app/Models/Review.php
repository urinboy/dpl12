<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'product_id', 
        'order_id',
        'rating',
        'comment',
        'pros',
        'cons',
        'is_approved',
        'is_verified_purchase'
    ];

    protected $casts = [
        'pros' => 'array',
        'cons' => 'array',
        'is_approved' => 'boolean',
        'is_verified_purchase' => 'boolean',
        'approved_at' => 'datetime'
    ];

    /**
     * Foydalanuvchi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mahsulot
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Buyurtma
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Sharh rasmlari
     */
    public function images()
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order');
    }

    /**
     * Helpfulness votes
     */
    public function helpfulnessVotes()
    {
        return $this->hasMany(ReviewHelpfulness::class);
    }

    /**
     * Foydali vote'lar
     */
    public function helpfulVotes()
    {
        return $this->helpfulnessVotes()->where('is_helpful', true);
    }

    /**
     * Foydali emas vote'lar
     */
    public function notHelpfulVotes()
    {
        return $this->helpfulnessVotes()->where('is_helpful', false);
    }

    /**
     * Rating yulduzlari array'i
     */
    public function getStarsArrayAttribute()
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = $i <= $this->rating;
        }
        return $stars;
    }

    /**
     * Review'ni tasdiqlash
     */
    public function approve()
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now()
        ]);

        // Mahsulot rating'ini yangilash
        $this->product->updateRatingStatistics();
    }

    /**
     * Review'ni rad etish
     */
    public function reject($adminComment = null)
    {
        $this->update([
            'is_approved' => false,
            'admin_comment' => $adminComment
        ]);

        // Mahsulot rating'ini yangilash
        $this->product->updateRatingStatistics();
    }

    /**
     * Foydalanuvchi bu review'ga vote berganmi?
     */
    public function hasUserVoted($userId)
    {
        return $this->helpfulnessVotes()
                   ->where('user_id', $userId)
                   ->exists();
    }

    /**
     * Foydalanuvchining vote'i
     */
    public function getUserVote($userId)
    {
        return $this->helpfulnessVotes()
                   ->where('user_id', $userId)
                   ->first();
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz', $userId = null)
    {
        $userVote = $userId ? $this->getUserVote($userId) : null;

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'is_verified_purchase' => $this->is_verified_purchase,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'user_vote' => $userVote ? $userVote->is_helpful : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->maskUserName($this->user->name),
                'avatar' => $this->user->avatar ? asset('storage/' . $this->user->avatar) : null
            ],
            'images' => $this->images->map(function($image) {
                return [
                    'id' => $image->id,
                    'url' => asset('storage/' . $image->image_path),
                    'thumbnail_url' => asset('storage/thumbnails/' . $image->image_path)
                ];
            })
        ];
    }

    /**
     * Foydalanuvchi nomini maskalash (privacy uchun)
     */
    protected function maskUserName($name)
    {
        if (strlen($name) <= 3) {
            return $name;
        }

        $firstChar = substr($name, 0, 1);
        $lastChar = substr($name, -1);
        $middleLength = strlen($name) - 2;
        
        return $firstChar . str_repeat('*', min($middleLength, 3)) . $lastChar;
    }

    /**
     * Scope: tasdiqlangan reviewlar
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: verified purchase'lar
     */
    public function scopeVerifiedPurchase($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    /**
     * Scope: rating bo'yicha
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }
}