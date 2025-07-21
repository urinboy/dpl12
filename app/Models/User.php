<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'address',
        'city_id',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Foydalanuvchi shahri
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Foydalanuvchi manzillari
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Foydalanuvchi buyurtmalari
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Foydalanuvchi savatchasi
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Default manzilni olish
     */
    public function getDefaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    /**
     * Foydalanuvchining aktiv buyurtmalari
     */
    public function getActiveOrders()
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Foydalanuvchining oxirgi buyurtmasi
     */
    public function getLastOrder()
    {
        return $this->orders()->orderBy('created_at', 'desc')->first();
    }

    /**
     * Umumiy buyurtmalar soni
     */
    public function getTotalOrdersCount()
    {
        return $this->orders()->count();
    }

    /**
     * Umumiy xaridlar summasi
     */
    public function getTotalSpent()
    {
        return $this->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');
    }

    /**
     * API uchun User ma'lumotlari (to'liq versiya)
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'address' => $this->address,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'city' => $this->city ? $this->city->toApiArray($locale) : null,
            'is_active' => $this->is_active,
            'statistics' => [
                'total_orders' => $this->getTotalOrdersCount(),
                'total_spent' => $this->getTotalSpent(),
                'addresses_count' => $this->addresses()->count(),
                'cart_items_count' => $this->cartItems()->count()
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Foydalanuvchi sharhlari
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Tasdiqlangan sharhlar
     */
    public function approvedReviews()
    {
        return $this->reviews()->approved();
    }

    /**
     * Review helpfulness votes
     */
    public function reviewHelpfulnessVotes()
    {
        return $this->hasMany(ReviewHelpfulness::class);
    }

    /**
     * Foydalanuvchi statistikalari (reviews bilan)
     */
    public function getReviewStatistics()
    {
        return [
            'total_reviews' => $this->reviews()->count(),
            'approved_reviews' => $this->approvedReviews()->count(),
            'average_rating_given' => $this->approvedReviews()->avg('rating'),
            'verified_reviews' => $this->reviews()->verifiedPurchase()->count()
        ];
    }

    /**
     * Foydalanuvchi mahsulotni sotib olganmi?
     */
    public function hasPurchasedProduct($productId)
    {
        return $this->orders()
            ->where('status', 'delivered')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })->exists();
    }

    /**
     * Foydalanuvchi qaysi mahsulotlarga sharh bera oladi?
     */
    public function getReviewableProducts()
    {
        // Sotib olingan mahsulotlar
        $purchasedProductIds = \App\Models\OrderItem::whereHas('order', function ($query) {
            $query->where('user_id', $this->id)
                ->where('status', 'delivered');
        })->pluck('product_id')->unique();

        // Allaqachon sharh berilgan mahsulotlar
        $reviewedProductIds = $this->reviews()->pluck('product_id');

        // Sharh berish mumkin bo'lgan mahsulotlar
        $reviewableProductIds = $purchasedProductIds->diff($reviewedProductIds);

        return \App\Models\Product::whereIn('id', $reviewableProductIds)
            ->where('is_active', true)
            ->get();
    }
}
