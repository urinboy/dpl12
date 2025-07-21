<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Barcha shaharlar ro'yxati
     */
    public function index()
    {
        $locale = app()->getLocale();
        
        $cities = City::where('is_active', true)
                     ->orderBy('id')
                     ->get();

        $data = $cities->map(function($city) use ($locale) {
            return $city->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Yetkazib berish mavjud shaharlar
     */
    public function deliveryAvailable()
    {
        $locale = app()->getLocale();
        
        $cities = City::where('is_active', true)
                     ->where('delivery_available', true)
                     ->orderBy('id')
                     ->get();

        $data = $cities->map(function($city) use ($locale) {
            return $city->toApiArray($locale);
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Shahar ma'lumotlari ID bo'yicha
     */
    public function show($id)
    {
        $locale = app()->getLocale();
        
        $city = City::where('is_active', true)->find($id);

        if (!$city) {
            return response()->json([
                'success' => false,
                'message' => 'City not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $city->toApiArray($locale)
        ]);
    }

    /**
     * Shahardagi yetkazib berish narxi
     */
    public function deliveryFee($id)
    {
        $city = City::where('is_active', true)
                   ->where('delivery_available', true)
                   ->find($id);

        if (!$city) {
            return response()->json([
                'success' => false,
                'message' => 'City not found or delivery not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'city_id' => $city->id,
                'delivery_fee' => $city->delivery_fee,
                'delivery_available' => $city->delivery_available
            ]
        ]);
    }
}