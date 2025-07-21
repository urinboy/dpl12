<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Foydalanuvchi profili (kengaytirilgan)
     */
    public function show(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        // Relationship'larni yuklash
        $user->load(['city', 'addresses' => function($query) {
            $query->orderBy('is_default', 'desc')->orderBy('created_at', 'desc');
        }]);

        // Statistikalarni olish
        $statistics = [
            'total_orders' => $user->orders()->count(),
            'completed_orders' => $user->orders()->where('status', 'delivered')->count(),
            'pending_orders' => $user->orders()->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])->count(),
            'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
            'total_spent' => $user->orders()->where('status', 'delivered')->sum('total_amount'),
            'total_reviews' => $user->reviews()->count(),
            'verified_reviews' => $user->reviews()->where('is_verified_purchase', true)->count(),
            'average_rating_given' => round($user->reviews()->avg('rating'), 2),
            'addresses_count' => $user->addresses()->count(),
            'wishlist_count' => 0, // Future feature
            'member_since' => $user->created_at->format('Y-m-d')
        ];

        // Recent orders
        $recentOrders = $user->orders()
                           ->orderBy('created_at', 'desc')
                           ->limit(3)
                           ->get()
                           ->map(function($order) use ($locale) {
                               return [
                                   'id' => $order->id,
                                   'order_number' => $order->order_number,
                                   'status' => $order->status,
                                   'status_label' => $order->getStatusLabel($locale),
                                   'total_amount' => $order->total_amount,
                                   'created_at' => $order->created_at->format('Y-m-d H:i:s')
                               ];
                           });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toApiArray($locale),
                'statistics' => $statistics,
                'recent_orders' => $recentOrders,
                'default_address' => $user->getDefaultAddress()?->toApiArray($locale)
            ]
        ]);
    }

    /**
     * Profil ma'lumotlarini yangilash
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id)
            ],
            'address' => 'nullable|string|max:500',
            'city_id' => 'nullable|exists:cities,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'phone', 'address', 'city_id']));

        $locale = app()->getLocale();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()->load('city')->toApiArray($locale)
        ]);
    }

    /**
     * Avatar yuklash
     */
    public function uploadAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048' // 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        try {
            // Eski avatarni o'chirish
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Yangi avatarni saqlash
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            
            $user->update(['avatar' => $avatarPath]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar_url' => asset('storage/' . $avatarPath)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Avatarni o'chirish
     */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if (!$user->avatar) {
            return response()->json([
                'success' => false,
                'message' => 'No avatar to delete'
            ], 400);
        }

        try {
            // Avatarni storage'dan o'chirish
            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->update(['avatar' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parol o'zgartirish
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Joriy parolni tekshirish
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Yangi parolni o'rnatish
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Barcha tokenlarni bekor qilish (xavfsizlik uchun)
        $user->tokens()->delete();

        // Yangi token berish
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
            'data' => [
                'token' => $token
            ]
        ]);
    }

    /**
     * Accountni o'chirish
     */
    public function deleteAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Parolni tekshirish
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 400);
        }

        // Aktiv buyurtmalar borligini tekshirish
        $activeOrders = $user->orders()
                           ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])
                           ->count();

        if ($activeOrders > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account with active orders'
            ], 400);
        }

        try {
            // Avatarni o'chirish
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Tokenlarni o'chirish
            $user->tokens()->delete();

            // Accountni deactivate qilish (to'liq o'chirmaslik)
            $user->update([
                'is_active' => false,
                'email' => 'deleted_' . time() . '@deleted.com',
                'phone' => null,
                'name' => 'Deleted User'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Profil statistikalari
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        $locale = app()->getLocale();

        // Oylik xaridlar statistikasi (oxirgi 12 oy)
        $monthlyOrders = $user->orders()
                            ->where('status', 'delivered')
                            ->where('created_at', '>=', now()->subMonths(12))
                            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count, SUM(total_amount) as total')
                            ->groupBy('month')
                            ->orderBy('month')
                            ->get();

        // Eng ko'p sotib olingan kategoriyalar
        $topCategories = \App\Models\OrderItem::whereHas('order', function($query) use ($user) {
                            $query->where('user_id', $user->id)
                                  ->where('status', 'delivered');
                        })
                        ->join('products', 'order_items.product_id', '=', 'products.id')
                        ->join('categories', 'products.category_id', '=', 'categories.id')
                        ->selectRaw('categories.id, COUNT(*) as count, SUM(order_items.total_price) as total_spent')
                        ->groupBy('categories.id')
                        ->orderBy('count', 'desc')
                        ->limit(5)
                        ->get()
                        ->map(function($item) use ($locale) {
                            $category = \App\Models\Category::find($item->id);
                            return [
                                'category' => $category?->toApiArray($locale),
                                'orders_count' => $item->count,
                                'total_spent' => $item->total_spent
                            ];
                        });

        // Eng ko'p sotib olingan mahsulotlar
        $topProducts = \App\Models\OrderItem::whereHas('order', function($query) use ($user) {
                            $query->where('user_id', $user->id)
                                  ->where('status', 'delivered');
                        })
                       ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(total_price) as total_spent, COUNT(*) as order_count')
                       ->groupBy('product_id')
                       ->orderBy('total_quantity', 'desc')
                       ->limit(5)
                       ->get()
                       ->map(function($item) use ($locale) {
                           $product = \App\Models\Product::find($item->product_id);
                           return [
                               'product' => [
                                   'id' => $product->id,
                                   'name' => $product->getTranslation('name', $locale),
                                   'image' => $product->primaryImage ? 
                                             asset('storage/' . $product->primaryImage->image_path) : null
                               ],
                               'total_quantity' => $item->total_quantity,
                               'total_spent' => $item->total_spent,
                               'order_count' => $item->order_count
                           ];
                       });

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_orders' => $monthlyOrders,
                'top_categories' => $topCategories,
                'top_products' => $topProducts
            ]
        ]);
    }

    /**
     * Notification settings
     */
    public function getNotificationSettings(Request $request)
    {
        $user = $request->user();

        // User settings (agar alohida jadval bo'lmasa, default values)
        $settings = [
            'order_updates' => true,
            'promotional_emails' => true,
            'new_products' => false,
            'price_drops' => true,
            'review_reminders' => true,
            'push_notifications' => true,
            'email_notifications' => true,
            'sms_notifications' => false
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_updates' => 'boolean',
            'promotional_emails' => 'boolean',
            'new_products' => 'boolean',
            'price_drops' => 'boolean',
            'review_reminders' => 'boolean',
            'push_notifications' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Bu yerda user_settings jadvaliga saqlash mumkin
        // Hozircha faqat response qaytaramiz

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
            'data' => $request->all()
        ]);
    }
}