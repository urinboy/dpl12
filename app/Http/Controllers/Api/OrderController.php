<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Foydalanuvchi buyurtmalari
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        $status = $request->get('status'); // pending, confirmed, delivered, cancelled
        $perPage = $request->get('per_page', 10);

        $query = Order::with(['items', 'deliveryCity'])
                     ->where('user_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')
                       ->paginate($perPage);

        $data = $orders->map(function($order) use ($locale) {
            return $order->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total()
            ]
        ]);
    }

    /**
     * Buyurtma batafsil ma'lumotlari
     */
    public function show(Request $request, $id)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        $order = Order::with(['items.product', 'deliveryCity'])
                     ->where('user_id', $user->id)
                     ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order->toApiArray($locale)
        ]);
    }

    /**
     * Yangi buyurtma berish
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required|exists:addresses,id',
            'delivery_date' => 'nullable|date|after:today',
            'delivery_time_slot' => 'nullable|string|max:50',
            'payment_method' => 'required|in:cash,card,online',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Manzilni tekshirish
        $address = Address::where('user_id', $user->id)->find($request->address_id);
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        // Savatdagi mahsulotlarni olish
        $cartItems = Cart::with('product')
                        ->where('user_id', $user->id)
                        ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Stock tekshirish
        foreach ($cartItems as $cartItem) {
            if ($cartItem->product->stock_quantity < $cartItem->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for {$cartItem->product->name}"
                ], 400);
            }
        }

        DB::beginTransaction();

        try {
            // Subtotal hisoblash
            $subtotal = $cartItems->sum(function($item) {
                return $item->price * $item->quantity;
            });

            // Yetkazib berish to'lovini olish
            $deliveryFee = $address->city->delivery_fee;

            // Umumiy summa
            $totalAmount = $subtotal + $deliveryFee;

            // Buyurtma yaratish
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => 0, // Keyinchalik promokod uchun
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'delivery_address' => [
                    'title' => $address->title,
                    'address' => $address->address,
                    'district' => $address->district,
                    'landmark' => $address->landmark,
                    'phone' => $address->phone ?? $user->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude
                ],
                'delivery_city_id' => $address->city_id,
                'delivery_date' => $request->delivery_date,
                'delivery_time_slot' => $request->delivery_time_slot,
                'notes' => $request->notes
            ]);

            // Order items yaratish
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->getTranslation('name', app()->getLocale()),
                    'product_sku' => $cartItem->product->sku,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->price,
                    'total_price' => $cartItem->price * $cartItem->quantity
                ]);

                // Stock kamaytirish
                $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
            }

            // Savatchani tozalash
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            $locale = app()->getLocale();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['items', 'deliveryCity'])->toApiArray($locale)
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buyurtmani qayta buyurtma qilish
     */
    public function reorder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items.product')
                     ->where('user_id', $user->id)
                     ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Savatchani tozalash
        Cart::where('user_id', $user->id)->delete();

        // Order items'larni savatga qo'shish
        foreach ($order->items as $item) {
            if ($item->product && $item->product->is_active) {
                $currentPrice = $item->product->discount_price && $item->product->discount_price < $item->product->price 
                               ? $item->product->discount_price 
                               : $item->product->price;

                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $currentPrice
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Items added to cart successfully'
        ]);
    }

    /**
     * Buyurtma status tarixi
     */
    public function statusHistory(Request $request, $id)
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $history = [];

        // Status tarixi yaratish
        $statusFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
        
        foreach ($statusFlow as $status) {
            $timestamp = null;
            $isCompleted = false;

            switch ($status) {
                case 'pending':
                    $timestamp = $order->created_at;
                    $isCompleted = true;
                    break;
                case 'confirmed':
                    $timestamp = $order->confirmed_at;
                    $isCompleted = $timestamp !== null;
                    break;
                case 'processing':
                    $isCompleted = in_array($order->status, ['processing', 'shipped', 'delivered']);
                    break;
                case 'shipped':
                    $timestamp = $order->shipped_at;
                    $isCompleted = $timestamp !== null;
                    break;
                case 'delivered':
                    $timestamp = $order->delivered_at;
                    $isCompleted = $timestamp !== null;
                    break;
            }

            if ($order->status === 'cancelled') {
                $isCompleted = $status === 'pending';
            }

            $history[] = [
                'status' => $status,
                'label' => $order->getStatusLabel($locale),
                'timestamp' => $timestamp?->format('Y-m-d H:i:s'),
                'is_completed' => $isCompleted,
                'is_current' => $order->status === $status
            ];
        }

        // Agar bekor qilingan bo'lsa
        if ($order->status === 'cancelled') {
            $history[] = [
                'status' => 'cancelled',
                'label' => $order->getStatusLabel($locale),
                'timestamp' => $order->cancelled_at?->format('Y-m-d H:i:s'),
                'is_completed' => true,
                'is_current' => true,
                'reason' => $order->cancellation_reason
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'history' => $history
            ]
        ]);
    }
}