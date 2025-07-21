<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    /**
     * Foydalanuvchi manzillari ro'yxati
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();

        $addresses = Address::with('city')
                           ->where('user_id', $user->id)
                           ->orderBy('is_default', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->get();

        $data = $addresses->map(function($address) use ($locale) {
            return $address->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Yangi manzil qo'shish
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'address' => 'required|string|max:500',
            'city_id' => 'required|exists:cities,id',
            'district' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $address = Address::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'address' => $request->address,
            'city_id' => $request->city_id,
            'district' => $request->district,
            'landmark' => $request->landmark,
            'phone' => $request->phone,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'is_default' => $request->is_default ?? false
        ]);

        // Agar default qilingan bo'lsa, boshqalarini default'dan chiqarish
        if ($address->is_default) {
            $address->setAsDefault();
        }

        $locale = app()->getLocale();

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address->load('city')->toApiArray($locale)
        ], 201);
    }

    /**
     * Manzilni yangilash
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:500',
            'city_id' => 'sometimes|exists:cities,id',
            'district' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $address->update($request->only([
            'title', 'address', 'city_id', 'district', 
            'landmark', 'phone', 'latitude', 'longitude', 'is_default'
        ]));

        // Agar default qilingan bo'lsa
        if ($request->has('is_default') && $request->is_default) {
            $address->setAsDefault();
        }

        $locale = app()->getLocale();

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address->load('city')->toApiArray($locale)
        ]);
    }

    /**
     * Manzilni o'chirish
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        // Default manzilni o'chirishga ruxsat bermaslik
        if ($address->is_default) {
            $otherAddresses = Address::where('user_id', $user->id)
                                   ->where('id', '!=', $id)
                                   ->exists();

            if ($otherAddresses) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default address. Please set another address as default first.'
                ], 400);
            }
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }

    /**
     * Default manzilni o'rnatish
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $address->setAsDefault();

        $locale = app()->getLocale();

        return response()->json([
            'success' => true,
            'message' => 'Default address set successfully',
            'data' => $address->load('city')->toApiArray($locale)
        ]);
    }
}