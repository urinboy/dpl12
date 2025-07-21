<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Mahsulotlar ro'yxati
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        
        $query = Product::with(['category', 'seller', 'images'])
                       ->where('is_active', true);

        // Kategoriya bo'yicha filtrlash
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Qidirish
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->whereHas('translations', function($q) use ($searchTerm, $locale) {
                $q->where('language_code', $locale)
                  ->where('field', 'name')
                  ->where('value', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Narx oralig'i
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Tavsiya etilgan mahsulotlar
        if ($request->has('featured') && $request->featured) {
            $query->where('is_featured', true);
        }

        // Sortlash
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'rating':
                $query->orderBy('rating_average', $sortOrder);
                break;
            case 'views':
                $query->orderBy('views_count', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', $sortOrder);
        }

        // Pagination
        $perPage = $request->get('per_page', 12);
        $products = $query->paginate($perPage);

        $data = $products->map(function($product) use ($locale) {
            return $product->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem()
            ]
        ]);
    }

    /**
     * Mahsulot batafsil ma'lumotlari
     */
    public function show($id)
    {
        $locale = app()->getLocale();
        
        $product = Product::with(['category', 'seller', 'images'])
                         ->where('is_active', true)
                         ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Ko'rishlar sonini oshirish
        $product->incrementViews();

        return response()->json([
            'success' => true,
            'data' => $product->toApiArray($locale)
        ]);
    }

    /**
     * Tavsiya etilgan mahsulotlar
     */
    public function featured()
    {
        $locale = app()->getLocale();
        
        $products = Product::with(['category', 'seller', 'images'])
                          ->where('is_active', true)
                          ->where('is_featured', true)
                          ->orderBy('created_at', 'desc')
                          ->limit(20)
                          ->get();

        $data = $products->map(function($product) use ($locale) {
            return $product->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Qidirish
     */
    public function search(Request $request)
    {
        $locale = app()->getLocale();
        $searchTerm = $request->get('q', '');

        if (empty($searchTerm)) {
            return response()->json([
                'success' => false,
                'message' => 'Search term is required'
            ], 400);
        }

        $products = Product::with(['category', 'seller', 'images'])
                          ->where('is_active', true)
                          ->whereHas('translations', function($q) use ($searchTerm, $locale) {
                              $q->where('language_code', $locale)
                                ->where(function($query) use ($searchTerm) {
                                    $query->where('field', 'name')
                                          ->where('value', 'LIKE', "%{$searchTerm}%")
                                          ->orWhere('field', 'description')
                                          ->where('value', 'LIKE', "%{$searchTerm}%");
                                });
                          })
                          ->orderBy('views_count', 'desc')
                          ->limit(50)
                          ->get();

        $data = $products->map(function($product) use ($locale) {
            return $product->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $products->count()
        ]);
    }

    /**
     * Kategoriya bo'yicha mahsulotlar
     */
    public function byCategory($categoryId)
    {
        $locale = app()->getLocale();
        
        $category = Category::where('is_active', true)->find($categoryId);
        
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $products = Product::with(['category', 'seller', 'images'])
                          ->where('is_active', true)
                          ->where('category_id', $categoryId)
                          ->orderBy('created_at', 'desc')
                          ->paginate(12);

        $data = $products->map(function($product) use ($locale) {
            return $product->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'category' => $category->toApiArray($locale),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total()
            ]
        ]);
    }
}