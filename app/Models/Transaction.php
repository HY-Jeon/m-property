<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'region_code',
        'legal_dong',
        'apartment_name',
        'jibun',
        'exclusive_area',
        'floor',
        'build_year',
        'deal_date',
        'deal_amount',
        'deal_type',
        'is_cancelled',
        'raw_hash',
    ];

    protected $casts = [
        'deal_date' => 'date',
        'exclusive_area' => 'decimal:2',
        'deal_amount' => 'integer',
        'is_cancelled' => 'boolean',
    ];
}
