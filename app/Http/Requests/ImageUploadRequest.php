<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'folder' => 'sometimes|string|in:products,avatars,reviews,categories',
            'resize' => 'sometimes|boolean',
            'width' => 'sometimes|integer|min:100|max:2000',
            'height' => 'sometimes|integer|min:100|max:2000'
        ];
    }

    public function messages()
    {
        return [
            'image.required' => 'Rasm yuklash majburiy',
            'image.image' => 'Fayl rasm bo\'lishi kerak',
            'image.mimes' => 'Faqat JPEG, PNG, JPG, GIF formatlar qabul qilinadi',
            'image.max' => 'Rasm hajmi 10MB dan kichik bo\'lishi kerak'
        ];
    }
}