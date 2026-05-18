<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\Client;
use App\Services\NumberingService;
use App\Services\BalanceService;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $receipts = Receipt::query()
            ->with(['client:id,name,code', 'creator:id,name', 'approver:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('receipt_number', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q2) => $q2->where('name', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'filters' => $request->only(['search', 'status']),
            'clients' => Client::where('is_active', true)->select('id', 'name', 'code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'amount_jod' => 'required|numeric|min:0.001',
            'payment_method' => 'required|in:cash,bank,check',
            'check_number' => 'nullable|string|max:50',
            'check_date' => 'nullable|date',
            'check_bank' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['receipt_number'] = NumberingService::generate('REC');
        $validated['receipt_date'] = now()->toDateString();
        $validated['status'] = 'pending';
        $validated['created_by'] = auth()->id();

        Receipt::create($validated);

        return redirect()->route('receipts.index')
            ->with('success', 'تم إنشاء سند القبض بنجاح');
    }

    public function approve(Receipt $receipt)
    {
        if (!$receipt->isPending()) {
            return back()->with('error', 'هذا السند ليس معلقاً');
        }

        DB::transaction(function () use ($receipt) {
            $receipt->approve(auth()->user());
            BalanceService::creditClient(
                $receipt->client,
                $receipt->amount_jod,
                'receipt',
                $receipt->id
            );
            // قيد محاسبي
            try { AccountingService::recordReceipt($receipt); } catch (\Exception $e) { \Log::error('Accounting Receipt: ' . $e->getMessage()); }
        });

        return back()->with('success', 'تم اعتماد سند القبض');
    }

    public function reject(Request $request, Receipt $receipt)
    {
        if (!$receipt->isPending()) {
            return back()->with('error', 'هذا السند ليس معلقاً');
        }

        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $receipt->reject(auth()->user(), $request->rejection_reason);

        return back()->with('success', 'تم رفض سند القبض');
    }

    public function destroy(Receipt $receipt)
    {
        if (!$receipt->isPending()) {
            return back()->with('error', 'لا يمكن حذف سند معتمد');
        }
        $receipt->delete();
        return redirect()->route('receipts.index')->with('success', 'تم حذف سند القبض');
    }

    public function print(Receipt $receipt)
    {
        $receipt->load(['client:id,name,code', 'creator:id,name', 'approver:id,name']);

        return Inertia::render('Receipts/Print', [
            'receipt' => $receipt,
        ]);
    }
}
