<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SafetyController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/transactions', [TransactionController::class, 'index']);
Route::get('/safety', [SafetyController::class, 'index']);
