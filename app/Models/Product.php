<?php
namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Translatable;

    protected $fillable = [
        'seller_id',
        'category_id',
        'price',
        'discount_price',
        'unit',
        'stock_quantity',
        'min_order_quantity',
        'sku',
        'barcode',
        'weight',
        'dimensions',
        'is_featured',
        'is_active',
        'views_count',
        'rating_average',
        'rating_count'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'rating_average' => 'decimal:2'
    ];

    // Tarjima qilinadigan fieldlar
    protected $translatable = ['name', 'description'];

    /**
     * Category bilan bog'lanish
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Seller bilan bog'lanish
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Product images
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Primary image
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Cart items
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Ko'rishlar sonini oshirish
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Chegirma foizi hisoblash
     */
    public function getDiscountPercentageAttribute()
    {
        if ($this->discount_price && $this->discount_price < $this->price) {
            return round((($this->price - $this->discount_price) / $this->price) * 100);
        }
        return 0;
    }

    /**
     * Joriy narx (chegirma bor bo'lsa chegirmali narx)
     */
    public function getCurrentPriceAttribute()
    {
        return $this->discount_price && $this->discount_price < $this->price
            ? $this->discount_price
            : $this->price;
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
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'current_price' => $this->current_price,
            'discount_percentage' => $this->discount_percentage,
            'unit' => $this->unit,
            'stock_quantity' => $this->stock_quantity,
            'min_order_quantity' => $this->min_order_quantity,
            'sku' => $this->sku,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'is_featured' => $this->is_featured,
            'views_count' => $this->views_count,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'category' => $this->category?->toApiArray($locale),
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'avatar' => $this->seller->avatar ? asset('storage/' . $this->seller->avatar) : null
            ],
            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => asset('storage/' . $image->image_path),
                    'alt_text' => $image->alt_text,
                    'is_primary' => $image->is_primary
                ];
            })
        ];
    }

    /**
     * Stock miqdorini kamaytirish
     */
    public function decrementStock($quantity)
    {
        if ($this->stock_quantity >= $quantity) {
            $this->decrement('stock_quantity', $quantity);
            return true;
        }
        return false;
    }

    /**
     * Stock miqdorini oshirish
     */
    public function incrementStock($quantity)
    {
        $this->increment('stock_quantity', $quantity);
    }

    /**
     * Mahsulot sharhlari
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
     * Verified purchase sharhlar
     */
    public function verifiedReviews()
    {
        return $this->reviews()->verifiedPurchase();
    }

    /**
     * Rating statistikasini yangilash
     */
    public function updateRatingStatistics()
    {
        $reviews = $this->approvedReviews;
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            $this->update([
                'rating_average' => 0,
                'rating_count' => 0,
                'rating_1_star' => 0,
                'rating_2_star' => 0,
                'rating_3_star' => 0,
                'rating_4_star' => 0,
                'rating_5_star' => 0,
                'reviews_count' => 0,
                'verified_reviews_count' => 0
            ]);
            return;
        }

        // Average rating
        $averageRating = $reviews->avg('rating');

        // Rating distribution
        $ratingDistribution = $reviews->groupBy('rating')
            ->map(function ($group) use ($totalReviews) {
                return ($group->count() / $totalReviews) * 100;
            })->toArray();

        // Verified reviews count
        $verifiedCount = $this->verifiedReviews()->count();

        $this->update([
            'rating_average' => round($averageRating, 2),
            'rating_count' => $totalReviews,
            'rating_1_star' => $ratingDistribution[1] ?? 0,
            'rating_2_star' => $ratingDistribution[2] ?? 0,
            'rating_3_star' => $ratingDistribution[3] ?? 0,
            'rating_4_star' => $ratingDistribution[4] ?? 0,
            'rating_5_star' => $ratingDistribution[5] ?? 0,
            'reviews_count' => $totalReviews,
            'verified_reviews_count' => $verifiedCount
        ]);
    }

    /**
     * Rating distributionini olish
     */
    public function getRatingDistribution()
    {
        return [
            '5' => $this->rating_5_star,
            '4' => $this->rating_4_star,
            '3' => $this->rating_3_star,
            '2' => $this->rating_2_star,
            '1' => $this->rating_1_star
        ];
    }

    /**
     * Foydalanuvchi bu mahsulotga sharh berganmi?
     */
    public function hasUserReviewed($userId)
    {
        return $this->reviews()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Foydalanuvchi bu mahsulotni sotib olganmi?
     */
    public function hasUserPurchased($userId)
    {
        return \App\Models\OrderItem::whereHas('order', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('status', 'delivered');
        })->where('product_id', $this->id)->exists();
    }

    /**
     * API uchun ma'lumot qaytarish (reviews bilan)
     */
    public function toApiArrayWithReviews($locale = 'uz', $userId = null)
    {
        $baseArray = $this->toApiArray($locale);

        // Review statistikalari qo'shish
        $baseArray['reviews'] = [
            'average_rating' => $this->rating_average,
            'total_count' => $this->reviews_count,
            'verified_count' => $this->verified_reviews_count,
            'distribution' => $this->getRatingDistribution(),
            'user_can_review' => $userId ? $this->canUserReview($userId) : false,
            'user_has_reviewed' => $userId ? $this->hasUserReviewed($userId) : false
        ];

        return $baseArray;
    }

    /**
     * Foydalanuvchi sharh bera oladimi?
     */
    public function canUserReview($userId)
    {
        // Allaqachon sharh berganmi?
        if ($this->hasUserReviewed($userId)) {
            return false;
        }

        // Mahsulotni sotib olganmi?
        return $this->hasUserPurchased($userId);
    }
}