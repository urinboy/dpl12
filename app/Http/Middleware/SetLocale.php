<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Language;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', 'uz');
        
        // Mavjud tillarni tekshirish
        try {
            $availableLocales = Language::where('is_active', true)->pluck('code')->toArray();
            
            if (!in_array($locale, $availableLocales)) {
                $defaultLanguage = Language::where('is_default', true)->first();
                $locale = $defaultLanguage ? $defaultLanguage->code : 'uz';
            }
        } catch (\Exception $e) {
            // Agar Language model mavjud bo'lmasa yoki DB xatolik bo'lsa
            $locale = 'uz';
        }

        app()->setLocale($locale);
        
        return $next($request);
    }
}