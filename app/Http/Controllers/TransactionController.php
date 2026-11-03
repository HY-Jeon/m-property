<?php

namespace App\Http\Controllers;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(): View
    {
        $transactions = Transaction::orderByDesc('deal_date')->limit(200)->get();
        $aggregates = MonthlyAggregate::orderBy('legal_dong')->orderBy('year_month')->get();

        $seriesData = $aggregates
            ->groupBy('legal_dong')
            ->map(function ($group) {
                return $group->map(function ($row) {
                    return [
                        'time' => substr($row->year_month, 0, 4) . '-' . substr($row->year_month, 4, 2) . '-01',
                        'value' => $row->avg_deal_amount,
                    ];
                })->values();
            })
            ->toArray();

        return view('transactions.index', compact('transactions', 'aggregates', 'seriesData'));
    }
}
