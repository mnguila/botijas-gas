<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    protected $fillable = ['type', 'value'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
