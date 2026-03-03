<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\Request;

class AdminCommissionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Список комиссий с пагинацией
    |--------------------------------------------------------------------------
    | GET /admin/commissions?from=2026-01-01&to=2026-01-31
    */
    public function index(Request $request)
    {
        $query = Commission::query()
            ->with(['trip', 'user'])
            ->orderByDesc('id');

        // Фильтр по дате
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $commissions = $query->paginate(20);

        return response()->json($commissions);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Общая статистика
    |--------------------------------------------------------------------------
    | GET /admin/commissions/stats?from=2026-01-01&to=2026-01-31
    */
    public function stats(Request $request)
    {
        $query = Commission::query();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalAmount = (clone $query)->sum('total_amount');
        $totalCommission = (clone $query)->sum('commission_amount');
        $totalRecords = (clone $query)->count();

        return response()->json([
            'total_turnover'   => $totalAmount,      // общий оборот
            'total_commission' => $totalCommission,  // твой доход
            'total_records'    => $totalRecords,     // сколько записей
        ]);
    }
}
