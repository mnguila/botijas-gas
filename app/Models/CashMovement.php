<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    protected $fillable = ['cash_account_id', 'operational_expense_id', 'type', 'amount_cents', 'note', 'created_by_user_id'];

    protected $casts = ['amount_cents' => 'integer'];

    public function account() { return $this->belongsTo(CashAccount::class, 'cash_account_id'); }
    public function expense() { return $this->belongsTo(OperationalExpense::class, 'operational_expense_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
