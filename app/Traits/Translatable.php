<?php
// app/Traits/Translatable.php yaratish

namespace App\Traits;

use App\Models\Translation;

trait Translatable
{
    /**
     * Tarjimalar bilan bog'lanish
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Ma'lum bir field uchun tarjima olish
     */
    public function getTranslation($field, $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        
        $translation = $this->translations()
            ->where('field', $field)
            ->where('language_code', $locale)
            ->first();

        return $translation ? $translation->value : null;
    }

    /**
     * Tarjima saqlash
     */
    public function setTranslation($field, $locale, $value)
    {
        return $this->translations()->updateOrCreate(
            [
                'field' => $field,
                'language_code' => $locale
            ],
            [
                'value' => $value
            ]
        );
    }

    /**
     * Barcha tillar uchun tarjima olish
     */
    public function getAllTranslations($field)
    {
        return $this->translations()
            ->where('field', $field)
            ->pluck('value', 'language_code')
            ->toArray();
    }

    /**
     * Magic method - name_uz, name_ru kabi atributlar uchun
     */
    public function getAttribute($key)
    {
        // Agar _uz, _ru, _en bilan tugasa
        if (preg_match('/^(.+)_(uz|ru|en)$/', $key, $matches)) {
            $field = $matches[1];
            $locale = $matches[2];
            
            return $this->getTranslation($field, $locale);
        }

        return parent::getAttribute($key);
    }
}
