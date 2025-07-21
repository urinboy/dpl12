<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Savatcha ma'lumotlari
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        $cartItems = Cart::with(['product.category', 'product.seller', 'product.images'])
                        ->where('user_id', $user->id)
                        ->get();

        $total = 0;
        $data = $cartItems->map(function($cartItem) use ($locale, &$total) {
            $itemTotal = $cartItem->price * $cartItem->quantity;
            $total += $itemTotal;

            return [
                'id' => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'total' => $itemTotal,
                'product' => $cartItem->product->toApiArray($locale)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $data,
                'total_amount' => $total,
                'items_count' => $cartItems->count()
            ]
        ]);
    }

    /**
     * Savatga mahsulot qo'shish
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
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
                'message' => 'Product not found or inactive'
            ], 404);
        }

        // Stock tekshirish
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 400);
        }

        // Minimum order quantity tekshirish
        if ($request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "Minimum order quantity is {$product->min_order_quantity}"
            ], 400);
        }

        $currentPrice = $product->discount_price && $product->discount_price < $product->price 
                       ? $product->discount_price 
                       : $product->price;

        $cartItem = Cart::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product->id
            ],
            [
                'quantity' => $request->quantity,
                'price' => $currentPrice
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'data' => [
                'id' => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'total' => $cartItem->price * $cartItem->quantity
            ]
        ]);
    }

    /**
     * Savatdagi mahsulot miqdorini yangilash
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)->find($id);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $product = $cartItem->product;

        // Stock tekshirish
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 400);
        }

        // Minimum order quantity tekshirish
        if ($request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "Minimum order quantity is {$product->min_order_quantity}"
            ], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully',
            'data' => [
                'id' => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'total' => $cartItem->price * $cartItem->quantity
            ]
        ]);
    }

    /**
     * Savatdan mahsulotni o'chirish
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)->find($id);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item removed successfully'
        ]);
    }

    /**
     * Savatchani tozalash
     */
    public function clear(Request $request)
    {
        $user = $request->user();
        Cart::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }
}