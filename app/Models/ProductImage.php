<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_primary'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean'
    ];

    /**
     * Mahsulot bilan bog'lanish
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * To'liq image URL
     */
    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    /**
     * Thumbnail URL
     */
    public function getThumbnailUrlAttribute(): string
    {
        $pathInfo = pathinfo($this->image_path);
        $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['basename'];
        return asset('storage/' . $thumbnailPath);
    }

    /**
     * File mavjudligini tekshirish
     */
    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->image_path);
    }

    /**
     * File o'lchamini olish (bytes)
     */
    public function getFileSizeAttribute(): ?int
    {
        if ($this->fileExists()) {
            return Storage::disk('public')->size($this->image_path);
        }
        return null;
    }

    /**
     * File formatini olish
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->image_path, PATHINFO_EXTENSION);
    }

    /**
     * Primary image qilish
     */
    public function setAsPrimary(): void
    {
        // Avval barcha rasmlarni primary emas qilish
        static::where('product_id', $this->product_id)
              ->where('id', '!=', $this->id)
              ->update(['is_primary' => false]);
        
        // Bu rasmni primary qilish
        $this->update(['is_primary' => true]);
    }

    /**
     * Sort order ni yangilash
     */
    public function updateSortOrder(int $newOrder): void
    {
        $this->update(['sort_order' => $newOrder]);
    }

    /**
     * Alt text ni avtomatik generatsiya qilish
     */
    public function generateAltText(): string
    {
        if ($this->product) {
            $productName = $this->product->getTranslation('name', app()->getLocale()) ?? 'Product';
            return $productName . ' - Image ' . ($this->sort_order + 1);
        }
        return 'Product Image';
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url,
            'alt_text' => $this->alt_text ?: $this->generateAltText(),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
            'file_extension' => $this->file_extension,
            'file_size' => $this->file_size,
            'file_exists' => $this->fileExists(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Scope: Primary image
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: Sort order bo'yicha
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Scope: Mahsulot bo'yicha
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Primary image ni olish (mahsulot uchun)
     */
    public static function getPrimaryForProduct(int $productId): ?self
    {
        return static::forProduct($productId)->primary()->first();
    }

    /**
     * Mahsulot uchun barcha rasmlarni olish
     */
    public static function getForProduct(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return static::forProduct($productId)->ordered()->get();
    }

    /**
     * Mahsulotning primary rasm URL ini olish
     */
    public static function getPrimaryImageUrl(int $productId): ?string
    {
        $primaryImage = static::getPrimaryForProduct($productId);
        return $primaryImage ? $primaryImage->image_url : null;
    }

    /**
     * Rasmlarni qayta tartibga solish
     */
    public static function reorderImages(int $productId, array $imageIds): void
    {
        foreach ($imageIds as $index => $imageId) {
            static::where('id', $imageId)
                  ->where('product_id', $productId)
                  ->update(['sort_order' => $index]);
        }
    }

    /**
     * Mahsulot uchun primary rasm o'rnatish
     */
    public static function setPrimaryForProduct(int $productId, int $imageId): bool
    {
        $image = static::where('id', $imageId)
                      ->where('product_id', $productId)
                      ->first();

        if ($image) {
            $image->setAsPrimary();
            return true;
        }

        return false;
    }

    /**
     * Faylni o'chirish (storage dan ham)
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            // Asosiy rasm
            Storage::disk('public')->delete($this->image_path);
            
            // Thumbnail (agar mavjud bo'lsa)
            $pathInfo = pathinfo($this->image_path);
            $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['basename'];
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Image o'lchamlarini olish
     */
    public function getImageDimensions(): ?array
    {
        if ($this->fileExists()) {
            $fullPath = Storage::disk('public')->path($this->image_path);
            $imageInfo = getimagesize($fullPath);
            
            if ($imageInfo) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'mime_type' => $imageInfo['mime']
                ];
            }
        }
        
        return null;
    }

    /**
     * Image optimallashtirilganmi tekshirish
     */
    public function isOptimized(): bool
    {
        $fileSize = $this->file_size;
        $dimensions = $this->getImageDimensions();
        
        if ($fileSize && $dimensions) {
            // 1MB dan katta va 2000px dan katta bo'lsa optimallashtirilmagan
            return !($fileSize > 1024 * 1024 && ($dimensions['width'] > 2000 || $dimensions['height'] > 2000));
        }
        
        return true;
    }

    /**
     * Boot method - model events
     */
    protected static function boot()
    {
        parent::boot();

        // Rasm yaratilganda
        static::creating(function ($image) {
            // Agar alt_text bo'sh bo'lsa, avtomatik generatsiya qilish
            if (empty($image->alt_text)) {
                $image->alt_text = $image->generateAltText();
            }

            // Agar sort_order berilmagan bo'lsa, oxiriga qo'shish
            if (is_null($image->sort_order)) {
                $maxOrder = static::where('product_id', $image->product_id)->max('sort_order');
                $image->sort_order = ($maxOrder ?? -1) + 1;
            }

            // Agar birinchi rasm bo'lsa, primary qilish
            $existingImagesCount = static::where('product_id', $image->product_id)->count();
            if ($existingImagesCount === 0) {
                $image->is_primary = true;
            }
        });

        // Primary rasm o'zgartirilganda
        static::updating(function ($image) {
            if ($image->isDirty('is_primary') && $image->is_primary) {
                // Boshqa rasmlarni primary emas qilish
                static::where('product_id', $image->product_id)
                      ->where('id', '!=', $image->id)
                      ->update(['is_primary' => false]);
            }
        });

        // Rasm o'chirilganda
        static::deleting(function ($image) {
            // Faylni storage dan o'chirish
            $image->deleteFile();

            // Agar primary rasm o'chirilayotgan bo'lsa
            if ($image->is_primary) {
                // Keyingi rasmni primary qilish
                $nextImage = static::where('product_id', $image->product_id)
                                   ->where('id', '!=', $image->id)
                                   ->ordered()
                                   ->first();
                
                if ($nextImage) {
                    $nextImage->update(['is_primary' => true]);
                }
            }
        });
    }
}