<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LesbuyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'name',
        'quantity',
        'unit',
        'notes',
        'selected_options',
        'image_url',
        'estimated_price',
        'actual_price',
        'is_checklist_item',
        'status',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'menu_item_id'      => 'integer',
        'estimated_price'   => 'decimal:2',
        'actual_price'      => 'decimal:2',
        'is_checklist_item' => 'boolean',
        'selected_options'  => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
