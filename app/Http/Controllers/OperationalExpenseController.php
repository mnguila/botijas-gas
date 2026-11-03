<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\OperationalExpense;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OperationalExpenseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny($request);
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected', 'cancelled'])],
        ]);
        $month = $data['month'] ?? now()->format('Y-m');
        $status = $data['status'] ?? 'all';
        $canSeeAll = $this->canSeeAll($request);
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->addMonth();
        $query = OperationalExpense::with(['createdBy', 'approvedBy', 'rejectedBy', 'cancelledBy'])
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<', $end->toDateString());
        if (! $canSeeAll) $query->where('created_by_user_id', $request->user()->id);
        $base = clone $query;
        if ($status !== 'all') $query->where('status', $status);
        $approvedCents = (int) (clone $base)->where('status', 'approved')->sum('amount_cents');
        $pendingCents = (int) (clone $base)->where('status', 'pending')->sum('amount_cents');
        $byCategory = (clone $base)->where('status', 'approved')->selectRaw('category, SUM(amount_cents) as total_cents')->groupBy('category')->orderByDesc('total_cents')->get();
        return view('finance.expenses', [
            'expenses' => $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(30)->withQueryString(),
            'categories' => config('finance.expense_categories'),
            'month' => $month, 'status' => $status, 'approvedCents' => $approvedCents,
            'pendingCents' => $pendingCents, 'byCategory' => $byCategory,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('expenses.create'), 403);
        $data = $request->validate([
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'category' => ['required', Rule::in(config('finance.expense_categories'))],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $amountCents = Money::toCents($data['amount']);
        if ($amountCents <= 0) throw ValidationException::withMessages(['amount' => 'Indique um valor superior a zero.']);
        $file = $request->file('receipt');
        $path = $file?->store('expense-receipts', 'local');
        if ($file && ! $path) throw new RuntimeException('Não foi possível guardar o comprovativo.');
        try {
            OperationalExpense::create([
                'expense_date' => $data['expense_date'], 'category' => $data['category'],
                'description' => trim($data['description']), 'amount_cents' => $amountCents,
                'location_id' => $request->user()->sales_location_id,
                'receipt_path' => $path, 'receipt_name' => $file ? basename($file->getClientOriginalName()) : null,
                'status' => 'pending', 'created_by_user_id' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return redirect()->route('expenses.index', ['month' => substr($data['expense_date'], 0, 7)])->with('success', 'Despesa registada e enviada para aprovação.');
    }

    public function approve(Request $request, OperationalExpense $expense)
    {
        abort_unless($request->user()->hasPermission('expenses.approve'), 403);
        DB::transaction(function () use ($request, $expense) {
            $expense = OperationalExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->status === 'pending', 409);
            abort_if(! $request->user()->is_super_admin && $expense->created_by_user_id === $request->user()->id, 403);
            $expense->update(['status' => 'approved', 'approved_by_user_id' => $request->user()->id, 'approved_at' => now()]);
        });
        return back()->with('success', 'Despesa aprovada e incluída nos totais operacionais.');
    }

    public function reject(Request $request, OperationalExpense $expense)
    {
        abort_unless($request->user()->hasPermission('expenses.approve'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $expense, $data) {
            $expense = OperationalExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->status === 'pending', 409);
            abort_if(! $request->user()->is_super_admin && $expense->created_by_user_id === $request->user()->id, 403);
            $expense->update(['status' => 'rejected', 'rejected_by_user_id' => $request->user()->id, 'rejected_at' => now(), 'rejection_reason' => trim($data['reason'])]);
        });
        return back()->with('success', 'Despesa rejeitada.');
    }

    public function cancel(Request $request, OperationalExpense $expense)
    {
        abort_unless($request->user()->hasPermission('expenses.approve'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $expense, $data) {
            $expense = OperationalExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->status === 'approved', 409);
            // Preserva a reconciliação de despesas antigas que chegaram a movimentar caixa.
            if ($expense->cash_account_id && CashMovement::where('operational_expense_id', $expense->id)->where('type', 'expense')->exists()) {
                $account = CashAccount::whereKey($expense->cash_account_id)->lockForUpdate()->firstOrFail();
                $account->increment('balance_cents', $expense->amount_cents);
                CashMovement::create([
                    'cash_account_id' => $account->id, 'operational_expense_id' => $expense->id,
                    'type' => 'reversal', 'amount_cents' => $expense->amount_cents,
                    'note' => 'Anulação da despesa #'.$expense->id.': '.trim($data['reason']),
                    'created_by_user_id' => $request->user()->id,
                ]);
            }
            $expense->update(['status' => 'cancelled', 'cancelled_by_user_id' => $request->user()->id, 'cancelled_at' => now(), 'cancellation_reason' => trim($data['reason'])]);
        });
        return back()->with('success', 'Despesa anulada. O histórico foi preservado.');
    }

    public function receipt(Request $request, OperationalExpense $expense)
    {
        $this->authorizeAny($request);
        abort_unless($this->canSeeAll($request) || $expense->created_by_user_id === $request->user()->id, 403);
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);
        return Storage::disk('local')->download($expense->receipt_path, $expense->receipt_name, ['Cache-Control' => 'private, no-store']);
    }

    private function canSeeAll(Request $request): bool
    {
        return $request->user()->is_super_admin || collect(['expenses.view', 'expenses.approve'])
            ->contains(fn ($permission) => $request->user()->hasPermission($permission));
    }

    private function authorizeAny(Request $request): void
    {
        abort_unless($request->user()->hasPermission('expenses.create') || $this->canSeeAll($request), 403);
    }
}
