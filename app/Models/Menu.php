<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Menu extends Model
{
    protected $guarded = ['id'];
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
