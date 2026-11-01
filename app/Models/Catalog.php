<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'original_name',
        'path',
        'first_page',
        'last_page',
        'status',
        'total_pages',
        'total_products',
        'cost_usd',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'first_page' => 'integer',
            'last_page' => 'integer',
            'total_pages' => 'integer',
            'total_products' => 'integer',
            'cost_usd' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
