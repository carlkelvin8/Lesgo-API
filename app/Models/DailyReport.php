<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date', 'total_orders', 'completed_orders', 'cancelled_orders',
        'new_users', 'new_drivers', 'total_revenue', 'avg_fare',
        'total_distance_km', 'meta',
    ];

    protected $casts = [
        'report_date' => 'date',
        'meta'        => 'array',
    ];
}
