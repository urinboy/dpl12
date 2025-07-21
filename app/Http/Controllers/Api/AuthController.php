<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Ro'yxatdan o'tish
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'city_id' => 'nullable|exists:cities,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'city_id' => $request->city_id,
            'role' => 'customer'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null
                ],
                'token' => $token
            ]
        ], 201);
    }

    /**
     * Kirish
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // email yoki phone
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        $user = User::where($loginField, $request->login)
                   ->where('is_active', true)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null
                ],
                'token' => $token
            ]
        ]);
    }

    /**
     * Chiqish
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Foydalanuvchi ma'lumotlari
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'address' => $user->address,
                    'city_id' => $user->city_id,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null
                ]
            ]
        ]);
    }

    /**
     * Foydalanuvchi profili (kengaytirilgan)
     */
    public function profile(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        // Relationship'larni yuklash
        $user->load(['city', 'addresses', 'orders' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(5);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toApiArray($locale),
                'recent_orders' => $user->orders->map(function($order) use ($locale) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'status_label' => $order->getStatusLabel($locale),
                        'total_amount' => $order->total_amount,
                        'created_at' => $order->created_at->format('Y-m-d H:i:s')
                    ];
                })
            ]
        ]);
    }
}