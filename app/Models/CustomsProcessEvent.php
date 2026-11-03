<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsProcessEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'actor_name', 'type', 'changes', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(CustomsProcess::class, 'customs_process_id');
    }
}
