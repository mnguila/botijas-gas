<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomsProcessTask extends Model
{
    protected $fillable = ['title', 'description', 'status', 'due_date', 'assigned_user_id', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(CustomsProcess::class, 'customs_process_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomsProcessDocument::class, 'customs_process_task_id');
    }
}
