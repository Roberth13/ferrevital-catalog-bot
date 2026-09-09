<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogLog extends Model
{
    protected $fillable = [
        'catalog_id',
        'codigo',
        'status',
        'message',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];
}
