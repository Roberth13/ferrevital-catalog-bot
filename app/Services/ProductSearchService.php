<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSearchService
{
    /**
     * Realiza una búsqueda determinística de productos sin depender de servicios externos de IA.
     *
     * @param array $filters Filtros soportados: 'search', 'catalog_id', 'supplier_id', 'per_page', 'page'
     * @return LengthAwarePaginator
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Product::with(['catalog', 'supplier']);

        $search = trim($filters['search'] ?? '');
        $catalogId = $filters['catalog_id'] ?? null;
        $supplierId = $filters['supplier_id'] ?? null;
        $perPage = $filters['per_page'] ?? 20;

        if (!empty($catalogId)) {
            $query->where('catalog_id', $catalogId);
        }

        if (!empty($supplierId)) {
            $query->where('supplier_id', $supplierId);
        }

        if (!empty($search)) {
            $this->applyDeterministicSearchAndRanking($query, $search);
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        $perPageInt = $perPage === 'all' ? ($query->count() ?: 1) : (int) $perPage;

        return $query->paginate($perPageInt)->withQueryString();
    }

    /**
     * Aplica el filtrado por término y el ranking determinístico basado en relevancia de campos y precio.
     */
    private function applyDeterministicSearchAndRanking(Builder $query, string $search): void
    {
        $searchTerm = '%' . $search . '%';
        $exactTerm = $search;
        $startsWithTerm = $search . '%';

        // Filtrar coincidencia en nombre, código o descripción
        $query->where(function (Builder $q) use ($searchTerm) {
            $q->where('nombre', 'like', $searchTerm)
              ->orWhere('codigo', 'like', $searchTerm)
              ->orWhere('descripcion', 'like', $searchTerm);
        });

        // Ponderación de relevancia determinística
        // 1. Coincidencia exacta de código (1000 pts)
        // 2. Código comienza por (500 pts)
        // 3. Coincidencia exacta de nombre (300 pts)
        // 4. Nombre comienza por (200 pts)
        // 5. Nombre contiene (100 pts)
        // 6. Descripción contiene (20 pts)
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Sintaxis de ordenamiento determinístico compatible con SQLite
            $query->orderByRaw("
                CASE 
                    WHEN codigo = ? THEN 1000
                    WHEN codigo LIKE ? THEN 500
                    WHEN LOWER(nombre) = LOWER(?) THEN 300
                    WHEN nombre LIKE ? THEN 200
                    WHEN nombre LIKE ? THEN 100
                    WHEN descripcion LIKE ? THEN 20
                    ELSE 0
                END DESC
            ", [$exactTerm, $startsWithTerm, $exactTerm, $startsWithTerm, $searchTerm, $searchTerm]);
        } else {
            // Sintaxis para MySQL / PostgreSQL
            $query->orderByRaw("
                CASE 
                    WHEN codigo = ? THEN 1000
                    WHEN codigo LIKE ? THEN 500
                    WHEN LOWER(nombre) = LOWER(?) THEN 300
                    WHEN nombre LIKE ? THEN 200
                    WHEN nombre LIKE ? THEN 100
                    WHEN descripcion LIKE ? THEN 20
                    ELSE 0
                END DESC
            ", [$exactTerm, $startsWithTerm, $exactTerm, $startsWithTerm, $searchTerm, $searchTerm]);
        }

        // Criterios secundarios de ranking determinístico: Menor precio primero (Value for money), luego ID
        $query->orderBy('precio_divisa', 'asc')
              ->orderBy('id', 'desc');
    }
}
