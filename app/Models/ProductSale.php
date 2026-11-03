<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSale extends Model
{
    protected $fillable = ['sale_order_id', 'product_id', 'channel', 'location_id', 'vehicle_location_id', 'quantity', 'unit_price', 'unit_cost', 'truck_plate', 'driver_name', 'created_by_user_id', 'note', 'sold_at', 'cancelled_at', 'cancelled_by_user_id'];

    protected $casts = ['sold_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function vehicleLocation()
    {
        return $this->belongsTo(Location::class, 'vehicle_location_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
