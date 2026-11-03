<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationRequestItem extends Model
{
    protected $fillable = ['product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function request()
    {
        return $this->belongsTo(AllocationRequest::class, 'allocation_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
