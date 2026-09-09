<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    protected $fillable = [
        'filename',
        'original_filename',
        'status',
        'total_products',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}