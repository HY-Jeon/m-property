<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyAggregate extends Model
{
    protected $fillable = [
        'region_code',
        'legal_dong',
        'year_month',
        'transaction_count',
        'avg_deal_amount',
        'min_deal_amount',
        'max_deal_amount',
    ];

    protected $casts = [
        'transaction_count' => 'integer',
        'avg_deal_amount' => 'integer',
        'min_deal_amount' => 'integer',
        'max_deal_amount' => 'integer',
    ];
}
