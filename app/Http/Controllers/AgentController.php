<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Events\DataUpdated;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::query()
            ->with('clients:id,name,code,phone,balance_jod,agent_id')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%"))
            ->when($request->status !== null, fn ($q) => $q->where('is_active', $request->boolean('status')))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Agents/Index', [
            'title' => 'الوكلاء',
            'agents' => $agents,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Agents/Form', [
            'title' => 'إضافة وكيل جديد',
            'agent' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'country' => 'required|in:JO,SA',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lastCode = Agent::withTrashed()->where('code', 'like', 'AGT-%')->orderByDesc('code')->value('code');
        $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
        $validated['code'] = 'AGT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $validated['currency'] = $validated['country'] === 'JO' ? 'JOD' : 'SAR';
        $validated['balance_sar'] = 0;
        $validated['is_active'] = true;

        $agent = Agent::create($validated);

        // === إنشاء حساب محاسبي مباشرة (بدون الاعتماد على Observer) ===
        if (!$agent->account_id) {
            $this->createAccountForAgent($agent);
        }

        event(new DataUpdated('agent', 'created', $agent->id));

        return redirect()->route('agents.index')
            ->with('success', 'تم إضافة الوكيل بنجاح');
    }

    public function show(Request $request, Agent $agent)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to = $request->to ?? now()->toDateString();

        $entries = LedgerEntry::where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->whereBetween('entry_date', [$from, $to . ' 23:59:59'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $summary = [
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
            'opening_balance' => LedgerEntry::where('entity_type', 'agent')
                ->where('entity_id', $agent->id)
                ->where('entry_date', '<', $from)
                ->orderByDesc('entry_date')->orderByDesc('id')
                ->value('balance_after') ?? 0,
        ];

        return Inertia::render('Agents/Show', [
            'title' => 'كشف حساب: ' . $agent->name,
            'agent' => $agent,
            'entries' => $entries,
            'summary' => $summary,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printStatement(Request $request, Agent $agent)
    {
        $from = $request->from ?: null;
        $to = $request->to ?: null;

        // 1. الحوالات المعتمدة (ما دفعناه للوكيل) + قيود التسوية الدائنة
        $transfersQuery = \App\Models\Transfer::where('agent_id', $agent->id)
            ->where('status', 'approved');
        if ($from && $to) {
            $transfersQuery->whereBetween('transfer_date', [$from, $to . ' 23:59:59']);
        }
        $transfers = $transfersQuery->get()->map(function ($t) {
            $paymentMethod = match ($t->payment_method) {
                'cash' => 'نقداً',
                'bank' => 'تحويل بنكي',
                'check' => 'شيك',
                default => $t->payment_method ?? '-',
            };
            return [
                'id' => 'TRF-' . $t->id,
                'date' => $t->transfer_date->format('Y-m-d'),
                'transfer_number' => $t->transfer_number,
                'amount_sar' => round($t->amount_sar, 2),
                'exchange_rate' => $t->exchange_rate,
                'payment_method' => $paymentMethod,
                'notes' => $t->notes ?? '',
                'type' => 'حوالة',
            ];
        });

        // قيود تسوية دائنة (تُضاف مع الحوالات لأنها تمثل "ما دفعناه للوكيل")
        $creditAdjQuery = \App\Models\LedgerEntry::where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->where('transaction_type', 'adjustment')
            ->where('credit', '>', 0);
        if ($from && $to) {
            $creditAdjQuery->whereBetween('entry_date', [$from, $to . ' 23:59:59']);
        }
        $creditAdj = $creditAdjQuery->get()->map(function ($entry) {
            return [
                'id' => 'JRN-' . $entry->id,
                'date' => $entry->entry_date->format('Y-m-d'),
                'transfer_number' => 'JRN-' . $entry->transaction_id,
                'amount_sar' => round($entry->credit, 2),
                'exchange_rate' => '-',
                'payment_method' => 'قيد تسوية',
                'notes' => $entry->description ?: 'قيد تسوية',
                'type' => 'قيد تسوية',
            ];
        });

        $allTransfers = $transfers->concat($creditAdj)->sortBy('date')->values();

        // 2. المخالفات المعتمدة + قيود التسوية المدينة
        $violationsQuery = \App\Models\Violation::where('agent_id', $agent->id)
            ->where('status', 'approved')
            ->with('violationType');
        if ($from && $to) {
            $violationsQuery->whereBetween('violation_date', [$from, $to . ' 23:59:59']);
        }
        $violations = $violationsQuery->get()->map(function ($v) {
            return [
                'id' => 'VIO-' . $v->id,
                'date' => $v->violation_date->format('Y-m-d'),
                'violation_number' => $v->violation_number,
                'type_name' => $v->violationType?->name ?? 'مخالفة',
                'passport_name' => $v->passport_name ?? '-',
                'description' => $v->description ?? '',
                'amount' => round($v->cost_sar, 2),
                'entry_type' => 'مخالفة',
            ];
        });

        // قيود تسوية مدينة (تُخصم من الوكيل)
        $debitAdjQuery = \App\Models\LedgerEntry::where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->where('transaction_type', 'adjustment')
            ->where('debit', '>', 0);
        if ($from && $to) {
            $debitAdjQuery->whereBetween('entry_date', [$from, $to . ' 23:59:59']);
        }
        $debitAdj = $debitAdjQuery->get()->map(function ($entry) {
            return [
                'id' => 'JRN-' . $entry->id,
                'date' => $entry->entry_date->format('Y-m-d'),
                'violation_number' => 'JRN-' . $entry->transaction_id,
                'type_name' => 'قيد تسوية',
                'passport_name' => '-',
                'description' => $entry->description ?: 'قيد تسوية',
                'amount' => round($entry->debit, 2),
                'entry_type' => 'قيد تسوية',
            ];
        });

        $allViolations = $violations->concat($debitAdj)->sortBy('date')->values();

        // 3. الملخص والرصيد الافتتاحي
        $openingBalance = 0;
        if ($from) {
            $openingBalance = \App\Models\LedgerEntry::where('entity_type', 'agent')
                ->where('entity_id', $agent->id)
                ->where('entry_date', '<', $from)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;
        }

        $transfersTotal = $allTransfers->sum('amount_sar');
        $violationsTotal = $allViolations->sum('amount');
        $balance = $openingBalance + $transfersTotal - $violationsTotal;

        $summary = [
            'opening_balance' => round($openingBalance, 2),
            'transfers_count' => $allTransfers->count(),
            'transfers_total' => round($transfersTotal, 2),
            'violations_count' => $allViolations->count(),
            'violations_total' => round($violationsTotal, 2),
            'balance' => round($balance, 2),
        ];

        // Template & Layout
        $template = \App\Models\Setting::where('key', 'print_template_accounting')->first();
        $templateUrl = $template?->value ? \Illuminate\Support\Facades\Storage::url($template->value) : null;
        $layoutSetting = \App\Models\Setting::where('key', 'print_layout_statement')->first();
        $layout = $layoutSetting?->value ? json_decode($layoutSetting->value, true) : null;

        return Inertia::render('Agents/PrintStatement', [
            'agent' => $agent,
            'transfers' => $allTransfers,
            'violations' => $allViolations,
            'summary' => $summary,
            'filters' => ['from' => $from ?? 'الكل', 'to' => $to ?? 'الكل'],
            'templateUrl' => $templateUrl,
            'layout' => $layout,
        ]);
    }

    public function edit(Agent $agent)
    {
        return Inertia::render('Agents/Form', [
            'title' => 'تعديل الوكيل: ' . $agent->name,
            'agent' => $agent,
        ]);
    }

    public function update(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'country' => 'required|in:JO,SA',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $validated['currency'] = $validated['country'] === 'JO' ? 'JOD' : 'SAR';
        $agent->update($validated);

        // مزامنة الحساب المحاسبي
        if ($agent->account_id) {
            Account::where('id', $agent->account_id)->update([
                'name' => $agent->name,
                'is_active' => $agent->is_active,
            ]);
        } else {
            $this->createAccountForAgent($agent);
        }

        return redirect()->route('agents.index')
            ->with('success', 'تم تحديث بيانات الوكيل بنجاح');
    }

    public function destroy(Agent $agent)
    {
        // منع حذف وكيل لديه أي عمليات مسجلة
        $hasLedger = LedgerEntry::where('entity_type', 'agent')
            ->where('entity_id', $agent->id)->exists();
        
        if ($hasLedger) {
            return back()->with('error', 'لا يمكن حذف وكيل تم إجراء عمليات مالية عليه.');
        }

        if ($agent->transfers()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف وكيل لديه حوالات مسجلة.');
        }

        if ($agent->clients()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف وكيل لديه عملاء مرتبطون.');
        }

        $agent->delete();

        return redirect()->route('agents.index')
            ->with('success', 'تم حذف الوكيل بنجاح');
    }

    /**
     * إنشاء حساب محاسبي للوكيل تحت 2110 الوكلاء
     */
    private function createAccountForAgent(Agent $agent): void
    {
        // ضمان وجود 2101 دائنون متنوعون
        $creditors = Account::where('code', '2101')->first();
        if (!$creditors) {
            $liabilities = Account::where('code', '2000')->first();
            if (!$liabilities) return;
            $creditors = Account::create([
                'code' => '2101', 'name' => 'دائنون متنوعون',
                'type' => 'liability', 'parent_id' => $liabilities->id,
                'is_system' => true, 'is_active' => true, 'currency' => 'JOD', 'balance' => 0,
            ]);
        }

        // ضمان وجود 2110 الوكلاء
        $agentsParent = Account::where('code', '2110')->first();
        if (!$agentsParent) {
            $agentsParent = Account::create([
                'code' => '2110', 'name' => 'الوكلاء',
                'type' => 'liability', 'parent_id' => $creditors->id,
                'is_system' => true, 'is_active' => true, 'currency' => 'JOD', 'balance' => 0,
            ]);
        }

        // إنشاء الحساب الفرعي للوكيل
        $newCode = \App\Services\AccountingSync::generateChildCode($agentsParent->id, $agentsParent->code);

        $account = Account::create([
            'code' => $newCode,
            'name' => $agent->name,
            'parent_id' => $agentsParent->id,
            'type' => 'liability',
            'is_active' => $agent->is_active ?? true,
            'is_system' => false,
            'currency' => 'JOD',
            'balance' => 0,
        ]);

        $agent->update(['account_id' => $account->id]);
    }
}
