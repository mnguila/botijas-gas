<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAllocation extends Model
{
    protected $fillable = ['product_id', 'location_id', 'quantity', 'created_by_user_id', 'note'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
