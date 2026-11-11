<?php

use App\Http\Controllers\SafetyController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/transactions');

Route::get('/transactions', [TransactionController::class, 'index']);
Route::get('/safety', [SafetyController::class, 'index']);
