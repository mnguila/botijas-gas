<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStandStock extends Model
{
    protected $fillable = ['product_id', 'location_id', 'quantity', 'damaged_quantity'];

    protected $casts = ['quantity' => 'integer', 'damaged_quantity' => 'integer'];

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->damaged_quantity;
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
