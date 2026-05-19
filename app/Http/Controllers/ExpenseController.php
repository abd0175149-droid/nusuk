<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::query()
            ->with(['category:id,name', 'creator:id,name', 'approver:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('expense_number', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'status']),
            'categories' => \App\Models\ExpenseCategory::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:SAR,JOD',
            'payment_method' => 'required|in:cash,bank,check',
            'expense_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['expense_number'] = NumberingService::generate('EXP');
        $validated['expense_date'] = $validated['expense_date'] ?? now()->toDateString();
        $validated['status'] = 'pending';
        $validated['created_by'] = auth()->id();

        Expense::create($validated);

        return redirect()->route('expenses.index')
            ->with('success', 'تم إنشاء المصروف بنجاح');
    }

    public function approve(Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'هذا المصروف ليس معلقاً');
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // قيد محاسبي
        try { \App\Services\AccountingService::recordExpense($expense); } catch (\Exception $e) { \Log::error('Accounting Expense: ' . $e->getMessage()); }

        // إشعار
        try { \App\Services\NotificationService::expenseApproved($expense); } catch (\Exception $e) {}

        return back()->with('success', 'تم اعتماد المصروف');
    }

    public function reject(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'هذا المصروف ليس معلقاً');
        }

        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $expense->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'تم رفض المصروف');
    }

    public function destroy(Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'لا يمكن حذف مصروف معتمد');
        }
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'تم حذف المصروف');
    }

    public function print(Expense $expense)
    {
        $expense->load(['category:id,name', 'creator:id,name', 'approver:id,name']);

        return Inertia::render('Expenses/Print', [
            'expense' => $expense,
        ]);
    }
}
