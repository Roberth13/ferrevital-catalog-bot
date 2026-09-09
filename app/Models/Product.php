<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'catalog_id',
        'codigo',
        'nombre',
        'precio_bs',
        'precio_divisa',
        'descripcion',
        'garantia',
        'condiciones',
        'tiempo_entrega',
        'raw_text',
        'confidence',
        'is_active',
    ];

    protected $casts = [
        'precio_bs' => 'decimal:2',
        'precio_divisa' => 'decimal:2',
        'confidence' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}