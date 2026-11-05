<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOrder extends Model
{
    protected $fillable = [
        'channel', 'customer_id', 'location_id', 'vehicle_location_id', 'truck_plate', 'driver_name',
        'created_by_user_id', 'note', 'sold_at', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
    ];

    protected $casts = ['sold_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(ProductSale::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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
