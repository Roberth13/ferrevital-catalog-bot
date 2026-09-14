<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function catalogs(): HasMany
    {
        return $this->hasMany(Catalog::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public static function getGenericSupplier(): self
    {
        return static::firstOrCreate(
            ['slug' => 'proveedor-generico'],
            ['name' => 'Proveedor Genérico']
        );
    }

    public static function getGenericSupplierId(): int
    {
        return static::getGenericSupplier()->id;
    }
}
