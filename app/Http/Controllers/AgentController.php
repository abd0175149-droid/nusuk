<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\LedgerEntry;
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
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Agents/Index', [
            'title' => 'الوكلاء',
            'agents' => $agents,
            'filters' => $request->only(['search', 'status']),
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

        $lastCode = Agent::where('code', 'like', 'AGT-%')->orderByDesc('code')->value('code');
        $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
        $validated['code'] = 'AGT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $validated['currency'] = $validated['country'] === 'JO' ? 'JOD' : 'SAR';
        $validated['balance_sar'] = 0;
        $validated['is_active'] = true;

        $agent = Agent::create($validated);

        // إنشاء حساب محاسبي فرعي تلقائياً
        try { \App\Services\AccountLinkService::createAgentAccount($agent); } catch (\Exception $e) {}

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
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to = $request->to ?? now()->toDateString();

        $entries = LedgerEntry::where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->whereBetween('entry_date', [$from, $to . ' 23:59:59'])
            ->orderBy('entry_date')->orderBy('id')->get();

        $summary = [
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
            'opening_balance' => LedgerEntry::where('entity_type', 'agent')
                ->where('entity_id', $agent->id)
                ->where('entry_date', '<', $from)
                ->orderByDesc('entry_date')->orderByDesc('id')
                ->value('balance_after') ?? 0,
        ];

        return Inertia::render('Statements/Print', [
            'entity' => $agent,
            'entries' => $entries,
            'summary' => $summary,
            'filters' => ['from' => $from, 'to' => $to],
            'type' => 'agent',
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
}
