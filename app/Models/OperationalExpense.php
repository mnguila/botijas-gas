<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalExpense extends Model
{
    protected $fillable = [
        'expense_date', 'category', 'description', 'amount_cents', 'location_id',
        'receipt_path', 'receipt_name', 'status',
        'created_by_user_id', 'approved_by_user_id', 'approved_at', 'rejected_by_user_id',
        'rejected_at', 'rejection_reason', 'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'expense_date' => 'date', 'amount_cents' => 'integer', 'approved_at' => 'datetime',
        'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function account() { return $this->belongsTo(CashAccount::class, 'cash_account_id'); }
    public function location() { return $this->belongsTo(Location::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function rejectedBy() { return $this->belongsTo(User::class, 'rejected_by_user_id'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
}
