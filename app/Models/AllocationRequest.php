<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationRequest extends Model
{
    protected $fillable = [
        'location_id', 'requested_by_user_id', 'status', 'note',
        'approved_by_user_id', 'approved_at', 'rejected_by_user_id', 'rejected_at', 'rejection_reason',
        'truck_plate', 'driver_name', 'dispatched_by_user_id', 'dispatched_at',
        'received_by_user_id', 'received_at', 'driver_document_path', 'driver_document_name',
    ];

    protected $casts = [
        'approved_at' => 'datetime', 'rejected_at' => 'datetime',
        'dispatched_at' => 'datetime', 'received_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(AllocationRequestItem::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
