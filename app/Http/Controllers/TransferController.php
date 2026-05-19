<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Services\NumberingService;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $transfers = Transfer::query()
            ->with(['agent:id,name,code', 'creator:id,name', 'approver:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('transfer_number', 'like', "%{$s}%")
                ->orWhereHas('agent', fn ($q2) => $q2->where('name', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->agent_id, fn ($q, $id) => $q->where('agent_id', $id))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'status', 'agent_id']),
            'agents' => Agent::where('is_active', true)->select('id', 'name', 'code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'amount_sar' => 'required|numeric|min:0.01',
            'cost_jod' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,bank,check',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['transfer_number'] = NumberingService::generate('TRF');
        $validated['transfer_date'] = now()->toDateString();
        $validated['status'] = 'pending';
        $validated['created_by'] = auth()->id();
        $validated['cost_jod'] = $validated['cost_jod'] ?? 0;
        $validated['exchange_rate'] = $validated['exchange_rate'] ?? 0;

        $transfer = Transfer::create($validated);

        return redirect()->route('transfers.index')
            ->with('success', "تم إنشاء الحوالة {$transfer->transfer_number} بنجاح");
    }

    public function approve(Transfer $transfer)
    {
        if (!$transfer->isPending()) {
            return back()->with('error', 'هذه الحوالة ليست معلقة');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->approve(auth()->user());
            BalanceService::creditAgent(
                $transfer->agent,
                $transfer->amount_sar,
                'transfer',
                $transfer->id
            );
            AuditLog::log('approve', 'transfer', $transfer->id, $transfer->transfer_number);
            // قيد محاسبي
            try { \App\Services\AccountingService::recordTransfer($transfer); } catch (\Exception $e) { \Log::error('Accounting Transfer: ' . $e->getMessage()); }
            try { \App\Services\NotificationService::transferApproved($transfer); } catch (\Exception $e) {}
        });

        return back()->with('success', "تم اعتماد الحوالة {$transfer->transfer_number}");
    }

    public function reject(Request $request, Transfer $transfer)
    {
        if (!$transfer->isPending()) {
            return back()->with('error', 'هذه الحوالة ليست معلقة');
        }

        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $transfer->reject(auth()->user(), $request->rejection_reason);
        AuditLog::log('reject', 'transfer', $transfer->id, $transfer->transfer_number);

        return back()->with('success', "تم رفض الحوالة {$transfer->transfer_number}");
    }

    public function destroy(Transfer $transfer)
    {
        if (!$transfer->isPending()) {
            return back()->with('error', 'لا يمكن حذف حوالة معتمدة أو مرفوضة');
        }

        $transfer->delete();
        return redirect()->route('transfers.index')
            ->with('success', 'تم حذف الحوالة');
    }
}
