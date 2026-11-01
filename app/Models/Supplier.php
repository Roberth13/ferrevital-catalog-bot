<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'condiciones',
        'tiempo_entrega',
        'garantia_default',
    ];

    public function catalogs(): HasMany
    {
        return $this->hasMany(Catalog::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
