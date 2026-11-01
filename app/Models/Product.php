<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'catalog_id',
        'codigo',
        'variante',
        'nombre',
        'precio_divisa',
        'precio_venta_usd',
        'precio_bs',
        'precio_venta_bs',
        'otros_precios',
        'condiciones_proveedor',
        'descripcion_tecnica',
        'incluye',
        'garantia',
        'pagina',
        'revisar',
        'revisar_motivo',
    ];

    protected function casts(): array
    {
        return [
            'precio_divisa' => 'decimal:2',
            'precio_venta_usd' => 'decimal:2',
            'precio_bs' => 'decimal:2',
            'precio_venta_bs' => 'decimal:2',
            'pagina' => 'integer',
            'revisar' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}
