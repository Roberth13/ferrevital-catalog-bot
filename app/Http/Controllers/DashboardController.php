<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\CatalogLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs (Tarjetas Principales)
        $totalProducts = Product::where('is_active', true)->count();
        $totalCatalogs = Catalog::count();
        
        // Calcular estado global desde los logs (Total histórico)
        $logsSummary = CatalogLog::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
            
        $created = $logsSummary['created'] ?? 0;
        $updated = $logsSummary['updated'] ?? 0;
        $failed = $logsSummary['failed'] ?? 0;
        $duplicated = $logsSummary['duplicated'] ?? 0;

        // Gráfico de líneas: Productos procesados últimos 7 días
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            return Carbon::today()->subDays($daysAgo)->format('Y-m-d');
        });

        $chartData = CatalogLog::select(
                DB::raw('DATE(created_at) as date'),
                'status',
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy('date', 'status')
            ->get();

        $lineChart = [
            'labels' => $last7Days->toArray(),
            'created' => [],
            'updated' => [],
            'failed' => [],
        ];

        foreach ($last7Days as $date) {
            $dayData = $chartData->where('date', $date);
            $lineChart['created'][] = $dayData->where('status', 'created')->sum('total');
            $lineChart['updated'][] = $dayData->where('status', 'updated')->sum('total');
            $lineChart['failed'][] = $dayData->where('status', 'failed')->sum('total');
        }

        // Listas Rápidas
        $recentCreatedProducts = Product::with('catalog')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Diferenciar actualizados (que no sean recién creados) usando updated_at > created_at
        $recentUpdatedProducts = Product::with('catalog')
            ->whereColumn('updated_at', '>', 'created_at')
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();

        $recentCatalogs = Catalog::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalProducts',
            'totalCatalogs',
            'created',
            'updated',
            'failed',
            'duplicated',
            'lineChart',
            'recentCreatedProducts',
            'recentUpdatedProducts',
            'recentCatalogs'
        ));
    }
}
