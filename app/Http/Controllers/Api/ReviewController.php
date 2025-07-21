<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\ReviewHelpfulness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * Mahsulot sharhlari ro'yxati
     */
    public function index(Request $request, $productId)
    {
        $locale = app()->getLocale();
        $user = $request->user();
        
        $product = Product::where('is_active', true)->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Query parameters
        $rating = $request->get('rating'); // 1-5
        $verified = $request->get('verified_only', false); // faqat verified purchase
        $sortBy = $request->get('sort_by', 'newest'); // newest, oldest, rating_high, rating_low, helpful
        $perPage = $request->get('per_page', 10);

        $query = $product->approvedReviews()
                        ->with(['user', 'images', 'helpfulnessVotes']);

        // Filtering
        if ($rating) {
            $query->where('rating', $rating);
        }

        if ($verified) {
            $query->where('is_verified_purchase', true);
        }

        // Sorting
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'rating_high':
                $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'rating_low':
                $query->orderBy('rating', 'asc')->orderBy('created_at', 'desc');
                break;
            case 'helpful':
                $query->orderBy('helpful_count', 'desc')->orderBy('created_at', 'desc');
                break;
            default: // newest
                $query->orderBy('created_at', 'desc');
        }

        $reviews = $query->paginate($perPage);

        $data = $reviews->map(function($review) use ($locale, $user) {
            return $review->toApiArray($locale, $user?->id);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $data,
                'product' => $product->toApiArrayWithReviews($locale, $user?->id),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total()
                ]
            ]
        ]);
    }

    /**
     * Sharh berish
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'pros' => 'nullable|array|max:5',
            'pros.*' => 'string|max:100',
            'cons' => 'nullable|array|max:5', 
            'cons.*' => 'string|max:100',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120' // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $product = Product::where('is_active', true)->find($request->product_id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Allaqachon sharh berganmi?
        if ($product->hasUserReviewed($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Verified purchase ekanligini tekshirish
            $isVerifiedPurchase = $product->hasUserPurchased($user->id);

            // Review yaratish
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'pros' => $request->pros,
                'cons' => $request->cons,
                'is_verified_purchase' => $isVerifiedPurchase,
                'is_approved' => true // Auto approve (yoki admin tasdiqi kerak bo'lsa false)
            ]);

            // Rasmlarni saqlash
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imagePath = $image->store('reviews', 'public');
                    
                    $review->images()->create([
                        'image_path' => $imagePath,
                        'original_name' => $image->getClientOriginalName(),
                        'file_size' => $image->getSize(),
                        'mime_type' => $image->getMimeType(),
                        'sort_order' => $index
                    ]);
                }
            }

            // Mahsulot rating statistikasini yangilash
            $product->updateRatingStatistics();

            DB::commit();

            $locale = app()->getLocale();

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review->load(['images', 'user'])->toApiArray($locale, $user->id)
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Foydalanuvchi sharhini yangilash
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $review = Review::where('user_id', $user->id)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // Faqat 24 soat ichida yangilash mumkin
        if ($review->created_at->diffInHours(now()) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'Review can only be edited within 24 hours'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'pros' => 'nullable|array|max:5',
            'pros.*' => 'string|max:100',
            'cons' => 'nullable|array|max:5',
            'cons.*' => 'string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $review->update($request->only(['rating', 'comment', 'pros', 'cons']));

            // Mahsulot rating statistikasini yangilash
            $review->product->updateRatingStatistics();

            DB::commit();

            $locale = app()->getLocale();

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review->load(['images', 'user'])->toApiArray($locale, $user->id)
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sharhni o'chirish
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $review = Review::where('user_id', $user->id)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // Faqat 24 soat ichida o'chirish mumkin
        if ($review->created_at->diffInHours(now()) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'Review can only be deleted within 24 hours'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $product = $review->product;
            
            // Rasmlarni o'chirish
            foreach ($review->images as $image) {
                if (Storage::disk('public')->exists($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }

            $review->delete();

            // Mahsulot rating statistikasini yangilash
            $product->updateRatingStatistics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sharh foydali/foydali emas deb belgilash
     */
    public function markHelpful(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_helpful' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $review = Review::where('is_approved', true)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // O'z sharhiga vote bera olmaydi
        if ($review->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot vote on your own review'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Oldingi vote'ni o'chirish/yangilash
            $existingVote = ReviewHelpfulness::where('review_id', $review->id)
                                           ->where('user_id', $user->id)
                                           ->first();

            if ($existingVote) {
                if ($existingVote->is_helpful === $request->is_helpful) {
                    // Bir xil vote - o'chirish
                    $existingVote->delete();
                    $message = 'Vote removed';
                } else {
                    // Farqli vote - yangilash
                    $existingVote->update(['is_helpful' => $request->is_helpful]);
                    $message = 'Vote updated';
                }
            } else {
                // Yangi vote
                ReviewHelpfulness::create([
                    'review_id' => $review->id,
                    'user_id' => $user->id,
                    'is_helpful' => $request->is_helpful
                ]);
                $message = 'Vote added';
            }

            // Review helpful count'larini yangilash
            $helpfulCount = $review->helpfulVotes()->count();
            $notHelpfulCount = $review->notHelpfulVotes()->count();

            $review->update([
                'helpful_count' => $helpfulCount,
                'not_helpful_count' => $notHelpfulCount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'helpful_count' => $helpfulCount,
                    'not_helpful_count' => $notHelpfulCount,
                    'user_vote' => $request->is_helpful
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process vote',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Foydalanuvchining barcha sharhlari
     */
    public function userReviews(Request $request)
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $perPage = $request->get('per_page', 10);

        $reviews = $user->reviews()
                       ->with(['product', 'images'])
                       ->orderBy('created_at', 'desc')
                       ->paginate($perPage);

        $data = $reviews->map(function($review) use ($locale, $user) {
            $reviewArray = $review->toApiArray($locale, $user->id);
            $reviewArray['product'] = [
                'id' => $review->product->id,
                'name' => $review->product->getTranslation('name', $locale),
                'image' => $review->product->primaryImage ? 
                          asset('storage/' . $review->product->primaryImage->image_path) : null
            ];
            return $reviewArray;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $data,
                'statistics' => $user->getReviewStatistics(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total()
                ]
            ]
        ]);
    }

    /**
     * Sharh berish mumkin bo'lgan mahsulotlar
     */
    public function reviewableProducts(Request $request)
    {
        $user = $request->user();
        $locale = app()->getLocale();

        $products = $user->getReviewableProducts();

        $data = $products->map(function($product) use ($locale) {
            return [
                'id' => $product->id,
                'name' => $product->getTranslation('name', $locale),
                'image' => $product->primaryImage ? 
                          asset('storage/' . $product->primaryImage->image_path) : null,
                'price' => $product->current_price,
                'purchased_at' => null // Bu yerda order date qo'shish mumkin
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Review statistics (admin uchun)
     */
    public function statistics()
    {
        $totalReviews = Review::count();
        $approvedReviews = Review::where('is_approved', true)->count();
        $pendingReviews = Review::where('is_approved', false)->count();
        $verifiedReviews = Review::where('is_verified_purchase', true)->count();

        $ratingDistribution = Review::where('is_approved', true)
                                  ->groupBy('rating')
                                  ->selectRaw('rating, count(*) as count')
                                  ->pluck('count', 'rating');

        $averageRating = Review::where('is_approved', true)->avg('rating');

        // Eng ko'p sharh berilgan mahsulotlar
        $topReviewedProducts = Product::withCount(['reviews' => function($query) {
                                      $query->where('is_approved', true);
                                  }])
                                  ->having('reviews_count', '>', 0)
                                  ->orderBy('reviews_count', 'desc')
                                  ->limit(10)
                                  ->get(['id', 'reviews_count']);

        // Eng faol foydalanuvchilar
        $topReviewers = \App\Models\User::withCount(['reviews' => function($query) {
                                     $query->where('is_approved', true);
                                 }])
                                 ->having('reviews_count', '>', 0)
                                 ->orderBy('reviews_count', 'desc')
                                 ->limit(10)
                                 ->get(['id', 'name', 'reviews_count']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $totalReviews,
                'approved_reviews' => $approvedReviews,
                'pending_reviews' => $pendingReviews,
                'verified_reviews' => $verifiedReviews,
                'average_rating' => round($averageRating, 2),
                'rating_distribution' => $ratingDistribution,
                'top_reviewed_products' => $topReviewedProducts,
                'top_reviewers' => $topReviewers
            ]
        ]);
    }

    /**
     * Sharh batafsil ma'lumotlari
     */
    public function show(Request $request, $id)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        $review = Review::with(['user', 'product', 'images', 'helpfulnessVotes'])
                       ->where('is_approved', true)
                       ->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $review->toApiArray($locale, $user?->id)
        ]);
    }

    /**
     * Admin uchun: Sharhni tasdiqlash
     */
    public function approve(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        $review->approve();

        return response()->json([
            'success' => true,
            'message' => 'Review approved successfully'
        ]);
    }

    /**
     * Admin uchun: Sharhni rad etish
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_comment' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        $review->reject($request->admin_comment);

        return response()->json([
            'success' => true,
            'message' => 'Review rejected successfully'
        ]);
    }
}