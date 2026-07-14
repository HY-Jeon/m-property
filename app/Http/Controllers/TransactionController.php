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
        $aggregates = MonthlyAggregate::orderByDesc('year_month')->orderBy('legal_dong')->get();

        return view('transactions.index', compact('transactions', 'aggregates'));
    }
}
