<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomsProcess extends Model
{
    protected $fillable = [
        'entry_number', 'entry_date', 'customer_id', 'invoice_reference',
        'invoice_attachment_path', 'invoice_attachment_name', 'subject',
        'at_technician', 'stage', 'communication',
        'communication_attachment_path', 'communication_attachment_name',
        'exit_date', 'archive_location', 'created_by_user_id', 'responsible_user_id',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'exit_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomsProcessTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomsProcessDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomsProcessEvent::class);
    }

    public function businessDaysElapsed(): ?int
    {
        if (! $this->entry_date) return null;
        $start = CarbonImmutable::parse($this->entry_date)->startOfDay();
        $end = $this->exit_date
            ? CarbonImmutable::parse($this->exit_date)->startOfDay()
            : CarbonImmutable::today(config('app.timezone'));

        $days = 0;
        for ($date = $start->addDay(); $date->lte($end); $date = $date->addDay()) {
            if (! $date->isWeekend()) $days++;
        }

        return $days;
    }

    public function getDisplayReferenceAttribute(): string
    {
        return $this->entry_number ?: 'Processo #'.$this->id;
    }
}
