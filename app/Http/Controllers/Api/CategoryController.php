<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Kategoriyalar ro'yxati
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $parentId = $request->query('parent_id');

        $query = Category::where('is_active', true);

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        }

        $categories = $query->orderBy('sort_order')
                           ->orderBy('id')
                           ->get();

        $data = $categories->map(function($category) use ($locale) {
            return $category->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Kategoriya batafsil ma'lumotlari
     */
    public function show($id)
    {
        $locale = app()->getLocale();
        
        $category = Category::where('is_active', true)->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category->toApiArray($locale)
        ]);
    }

    /**
     * Asosiy kategoriyalar (parent_id = null)
     */
    public function main()
    {
        $locale = app()->getLocale();

        $categories = Category::where('is_active', true)
                             ->whereNull('parent_id')
                             ->orderBy('sort_order')
                             ->get();

        $data = $categories->map(function($category) use ($locale) {
            return $category->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
