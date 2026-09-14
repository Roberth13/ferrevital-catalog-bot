<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'supplier_id',
        'catalog_id',
        'page_number',
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
        'extraction_method',
        'ai_provider',
        'ai_model',
        'prompt_version',
        'parser_version',
        'precio_venta_bs',
        'precio_venta_divisa',
        'sale_price_formula',
        'sale_price_base',
        'sale_price_applied_at',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'precio_bs' => 'decimal:2',
        'precio_divisa' => 'decimal:2',
        'precio_venta_bs' => 'decimal:2',
        'precio_venta_divisa' => 'decimal:2',
        'sale_price_applied_at' => 'datetime',
        'confidence' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}