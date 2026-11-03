<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $fillable = ['name', 'location_id', 'balance_cents', 'is_active'];

    protected $casts = ['balance_cents' => 'integer', 'is_active' => 'boolean'];

    public function location() { return $this->belongsTo(Location::class); }
    public function movements() { return $this->hasMany(CashMovement::class); }
}
