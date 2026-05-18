<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Agent;
use App\Models\Client;
use App\Models\ViolationType;
use App\Models\AuditLog;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViolationController extends Controller
{
    public function index(Request $request)
    {
        $violations = Violation::query()
            ->with(['agent:id,name,code', 'client:id,name,code', 'violationType:id,name', 'creator:id,name', 'approver:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('violation_number', 'like', "%{$s}%")
                ->orWhere('passport_name', 'like', "%{$s}%")
                ->orWhere('passport_number', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->billing, fn ($q, $s) => $q->where('billing_status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Violations/Index', [
            'title' => 'المخالفات',
            'violations' => $violations,
            'filters' => $request->only(['search', 'status', 'billing']),
            'agents' => Agent::where('is_active', true)->select('id', 'name', 'code')->get(),
            'clients' => Client::where('is_active', true)->select('id', 'name', 'code')->get(),
            'violationTypes' => ViolationType::where('is_active', true)->select('id', 'name', 'default_cost_sar')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'client_id' => 'required|exists:clients,id',
            'violation_type_id' => 'required|exists:violation_types,id',
            'passport_number' => 'nullable|string|max:50',
            'passport_name' => 'nullable|string|max:255',
            'cost_sar' => 'required|numeric|min:0.01',
            'violation_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        // ترقيم تلقائي
        $today = now()->format('Ymd');
        $lastNum = Violation::where('violation_number', 'like', "VIO-{$today}-%")->orderByDesc('violation_number')->value('violation_number');
        $seq = $lastNum ? (int)substr($lastNum, -4) + 1 : 1;
        $validated['violation_number'] = "VIO-{$today}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $validated['status'] = 'pending';
        $validated['billing_status'] = 'unbilled';
        $validated['created_by'] = auth()->id();

        Violation::create($validated);

        return redirect()->back()->with('success', 'تم تسجيل المخالفة بنجاح');
    }

    public function approve(Violation $violation)
    {
        if (!$violation->isPending()) {
            return back()->with('error', 'هذه المخالفة ليست معلقة');
        }

        DB::transaction(function () use ($violation) {
            $violation->approve();

            // خصم التكلفة من رصيد الوكيل
            BalanceService::debitAgent(
                $violation->agent,
                $violation->cost_sar,
                'violation',
                $violation->id
            );
            // قيد محاسبي
            try { \App\Services\AccountingService::recordViolation($violation); } catch (\Exception $e) { \Log::error('Accounting Violation: ' . $e->getMessage()); }
        });

        AuditLog::log('approve', 'violation', $violation->id, $violation->violation_number);
        return back()->with('success', 'تم اعتماد المخالفة وخصم التكلفة من الوكيل');
    }

    public function reject(Request $request, Violation $violation)
    {
        if (!$violation->isPending()) {
            return back()->with('error', 'هذه المخالفة ليست معلقة');
        }

        $violation->reject($request->input('reason', ''));
        return back()->with('success', 'تم رفض المخالفة');
    }

    public function destroy(Violation $violation)
    {
        if ($violation->status !== 'pending') {
            return back()->with('error', 'لا يمكن حذف مخالفة معتمدة أو مرفوضة');
        }
        $violation->delete();
        return back()->with('success', 'تم حذف المخالفة');
    }
}
