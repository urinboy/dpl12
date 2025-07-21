<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2'
    ];

    /**
     * Foydalanuvchi bilan bog'lanish
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mahsulot bilan bog'lanish
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Umumiy narx (quantity * price)
     */
    public function getTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * Joriy mahsulot narxi (chegirma bilan)
     */
    public function getCurrentProductPriceAttribute(): float
    {
        if (!$this->product) {
            return $this->price;
        }

        return $this->product->discount_price && $this->product->discount_price < $this->product->price
            ? $this->product->discount_price
            : $this->product->price;
    }

    /**
     * Narx o'zgarganmi tekshirish
     */
    public function isPriceChanged(): bool
    {
        return $this->price != $this->current_product_price;
    }

    /**
     * Mahsulot mavjudmi va aktiv ekanini tekshirish
     */
    public function isProductAvailable(): bool
    {
        return $this->product && $this->product->is_active;
    }

    /**
     * Stock yetarli ekanini tekshirish
     */
    public function hasEnoughStock(): bool
    {
        if (!$this->product) {
            return false;
        }

        return $this->product->stock_quantity >= $this->quantity;
    }

    /**
     * Minimum buyurtma miqdorini tekshirish
     */
    public function meetsMinimumOrderQuantity(): bool
    {
        if (!$this->product) {
            return false;
        }

        return $this->quantity >= $this->product->min_order_quantity;
    }

    /**
     * Miqdorni yangilash (validatsiya bilan)
     */
    public function updateQuantity(int $quantity): bool
    {
        if ($quantity <= 0) {
            $this->delete();
            return true;
        }

        // Stock tekshirish
        if (!$this->product || $this->product->stock_quantity < $quantity) {
            return false;
        }

        // Minimum order quantity tekshirish
        if ($quantity < $this->product->min_order_quantity) {
            return false;
        }

        $this->update(['quantity' => $quantity]);
        return true;
    }

    /**
     * Narxni yangilash (joriy mahsulot narxi bilan)
     */
    public function updatePrice(): void
    {
        if ($this->product) {
            $currentPrice = $this->current_product_price;
            if ($this->price != $currentPrice) {
                $this->update(['price' => $currentPrice]);
            }
        }
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray(string $locale = 'uz'): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->total,
            'is_price_changed' => $this->isPriceChanged(),
            'current_product_price' => $this->current_product_price,
            'is_available' => $this->isProductAvailable(),
            'has_enough_stock' => $this->hasEnoughStock(),
            'meets_minimum_quantity' => $this->meetsMinimumOrderQuantity(),
            'product' => $this->product ? $this->product->toApiArray($locale) : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Scope: Foydalanuvchi bo'yicha
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Mavjud mahsulotlar bilan
     */
    public function scopeWithAvailableProducts($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope: Stock mavjud bo'lgan mahsulotlar
     */
    public function scopeWithStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->whereRaw('stock_quantity >= carts.quantity');
        });
    }

    /**
     * Foydalanuvchining savatcha statistikasi
     */
    public static function getUserCartStats(int $userId): array
    {
        $cartItems = static::forUser($userId)->with('product')->get();
        
        $totalItems = $cartItems->count();
        $totalQuantity = $cartItems->sum('quantity');
        $totalAmount = $cartItems->sum(function ($item) {
            return $item->total;
        });
        
        $unavailableItems = $cartItems->filter(function ($item) {
            return !$item->isProductAvailable();
        })->count();
        
        $outOfStockItems = $cartItems->filter(function ($item) {
            return !$item->hasEnoughStock();
        })->count();
        
        $priceChangedItems = $cartItems->filter(function ($item) {
            return $item->isPriceChanged();
        })->count();

        return [
            'total_items' => $totalItems,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'unavailable_items' => $unavailableItems,
            'out_of_stock_items' => $outOfStockItems,
            'price_changed_items' => $priceChangedItems,
            'is_valid' => $unavailableItems === 0 && $outOfStockItems === 0
        ];
    }

    /**
     * Savatcha tozalash (foydalanuvchi uchun)
     */
    public static function clearForUser(int $userId): int
    {
        return static::forUser($userId)->delete();
    }

    /**
     * Mavjud bo'lmagan mahsulotlarni o'chirish
     */
    public static function removeUnavailableItems(int $userId): int
    {
        return static::forUser($userId)
            ->whereDoesntHave('product', function ($q) {
                $q->where('is_active', true);
            })
            ->delete();
    }

    /**
     * Narxlarni yangilash (barcha savatcha elementlari uchun)
     */
    public static function updatePricesForUser(int $userId): int
    {
        $cartItems = static::forUser($userId)->with('product')->get();
        $updatedCount = 0;

        foreach ($cartItems as $cartItem) {
            if ($cartItem->isPriceChanged()) {
                $cartItem->updatePrice();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Boot method - model events
     */
    protected static function boot()
    {
        parent::boot();

        // Savatga qo'shilganda mahsulot narxini avtomatik o'rnatish
        static::creating(function ($cart) {
            if (!$cart->price && $cart->product) {
                $cart->price = $cart->current_product_price;
            }
        });

        // Model o'chirilganda additional cleanup (agar kerak bo'lsa)
        static::deleting(function ($cart) {
            // Agar kerak bo'lsa, qo'shimcha cleanup logic
        });
    }
}