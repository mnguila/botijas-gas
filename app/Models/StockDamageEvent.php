<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDamageEvent extends Model
{
    protected $fillable = [
        'product_id', 'location_id', 'product_sale_id', 'kind', 'quantity',
        'refund_cents', 'reason', 'created_by_user_id', 'received_location_id', 'supplier_name', 'reference',
    ];

    protected $casts = ['quantity' => 'integer', 'refund_cents' => 'integer'];

    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function location() { return $this->belongsTo(Location::class); }
    public function receivedLocation() { return $this->belongsTo(Location::class, 'received_location_id'); }
    public function saleItem() { return $this->belongsTo(ProductSale::class, 'product_sale_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
