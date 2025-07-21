<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'delivery_address',
        'delivery_city_id',
        'delivery_date',
        'delivery_time_slot',
        'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_address' => 'array',
        'delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    /**
     * Foydalanuvchi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Buyurtma elementlari
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Yetkazib berish shahri
     */
    public function deliveryCity()
    {
        return $this->belongsTo(City::class, 'delivery_city_id');
    }

    /**
     * Buyurtma raqamini generatsiya qilish
     */
    public static function generateOrderNumber()
    {
        $year = date('Y');
        $lastOrder = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastOrder ? (int) substr($lastOrder->order_number, -6) + 1 : 1;

        return 'ORD-' . $year . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Status o'zgartirish
     */
    public function updateStatus($status, $reason = null)
    {
        $this->status = $status;

        switch ($status) {
            case 'confirmed':
                $this->confirmed_at = now();
                break;
            case 'shipped':
                $this->shipped_at = now();
                break;
            case 'delivered':
                $this->delivered_at = now();
                $this->payment_status = 'paid'; // Yetkazilganda to'langan deb hisoblaymiz
                break;
            case 'cancelled':
                $this->cancelled_at = now();
                $this->cancellation_reason = $reason;
                break;
        }

        $this->save();
    }

    /**
     * Buyurtmani bekor qilish mumkinmi?
     */
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel($locale),
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'delivery_address' => $this->delivery_address,
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'delivery_time_slot' => $this->delivery_time_slot,
            'notes' => $this->notes,
            'can_be_cancelled' => $this->canBeCancelled(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'delivery_city' => $this->deliveryCity?->toApiArray($locale),
            'items' => $this->items->map(function ($item) use ($locale) {
                return $item->toApiArray($locale);
            })
        ];
    }

    /**
     * Status labelini olish
     */
    public function getStatusLabel($locale = 'uz')
    {
        $labels = [
            'uz' => [
                'pending' => 'Kutilmoqda',
                'confirmed' => 'Tasdiqlangan',
                'processing' => 'Tayyorlanmoqda',
                'shipped' => 'Yuborilgan',
                'delivered' => 'Yetkazilgan',
                'cancelled' => 'Bekor qilingan'
            ],
            'ru' => [
                'pending' => 'Ожидает',
                'confirmed' => 'Подтверждён',
                'processing' => 'Готовится',
                'shipped' => 'Отправлен',
                'delivered' => 'Доставлен',
                'cancelled' => 'Отменён'
            ]
        ];

        return $labels[$locale][$this->status] ?? $this->status;
    }

    /**
     * Bu buyurtmadagi mahsulotlar uchun sharh berish mumkinmi?
     */
    public function canBeReviewed()
    {
        return $this->status === 'delivered' &&
            $this->delivered_at &&
            $this->delivered_at->diffInDays(now()) <= 365; // 1 yil ichida
    }

    /**
     * Bu buyurtmadagi sharhlanmagan mahsulotlar
     */
    public function getUnreviewedProducts()
    {
        $reviewedProductIds = Review::where('user_id', $this->user_id)
            ->whereIn('product_id', $this->items()->pluck('product_id'))
            ->pluck('product_id');

        return $this->items()
            ->whereNotIn('product_id', $reviewedProductIds)
            ->with('product')
            ->get();
    }
}
