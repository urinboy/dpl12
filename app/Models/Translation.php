<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'language_code',
        'field',
        'value'
    ];

    /**
     * Translatable model bilan bog'lanish
     */
    public function translatable()
    {
        return $this->morphTo();
    }

    /**
     * Language bilan bog'lanish
     */
    public function language()
    {
        return $this->belongsTo(Language::class, 'language_code', 'code');
    }
}