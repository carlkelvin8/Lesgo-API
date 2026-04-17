<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'menu_category_id',
        'name',
        'description',
        'image_url',
        'price',
        'original_price',
        'unit',
        'is_available',
        'is_popular',
        'is_featured',
        'is_best_seller',
        'requires_prescription',
        'sort_order',
        'tags',
        'options',
    ];

    protected $casts = [
        'price'                  => 'decimal:2',
        'original_price'         => 'decimal:2',
        'is_available'           => 'boolean',
        'is_popular'             => 'boolean',
        'is_featured'            => 'boolean',
        'is_best_seller'         => 'boolean',
        'requires_prescription'  => 'boolean',
        'sort_order'             => 'integer',
        'tags'                   => 'array',
        'options'                => 'array',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }
}
