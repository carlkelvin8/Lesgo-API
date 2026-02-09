<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LesbuyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'name',
        'quantity',
        'estimated_price',
        'is_checklist_item',
        'status',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'estimated_price'   => 'decimal:2',
        'is_checklist_item' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
