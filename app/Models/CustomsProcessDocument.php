<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsProcessDocument extends Model
{
    protected $fillable = ['customs_process_id', 'path', 'original_name', 'customs_process_task_id', 'uploaded_by_user_id'];

    public function process(): BelongsTo
    {
        return $this->belongsTo(CustomsProcess::class, 'customs_process_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CustomsProcessTask::class, 'customs_process_task_id');
    }
}
