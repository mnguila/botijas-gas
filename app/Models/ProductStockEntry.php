<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockEntry extends Model
{
    protected $fillable = ['product_id', 'created_by_user_id', 'quantity_added', 'quantity_before', 'quantity_after', 'source', 'note'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
