<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%"))
            ->when($request->status !== null, fn ($q) => $q->where('is_active', $request->boolean('status')))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'title' => 'العملاء',
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function show(Request $request, Client $client)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to = $request->to ?? now()->toDateString();

        $entries = LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->whereBetween('entry_date', [$from, $to . ' 23:59:59'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $summary = [
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
            'opening_balance' => LedgerEntry::where('entity_type', 'client')
                ->where('entity_id', $client->id)
                ->where('entry_date', '<', $from)
                ->orderByDesc('entry_date')->orderByDesc('id')
                ->value('balance_after') ?? 0,
        ];

        return Inertia::render('Clients/Show', [
            'title' => 'كشف حساب: ' . $client->name,
            'client' => $client,
            'entries' => $entries,
            'summary' => $summary,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printStatement(Request $request, Client $client)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        // 1. حركات كشف الحساب للعميل
        $entries = \App\Models\LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->whereBetween('entry_date', [$from, $to . ' 23:59:59'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // 2. الرصيد الافتتاحي قبل الفترة
        $openingBalance = \App\Models\LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->where('entry_date', '<', $from)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $endingBalance = $openingBalance + $totalDebit - $totalCredit;

        $summary = [
            'opening_balance' => round($openingBalance, 3),
            'total_debit' => round($totalDebit, 3),
            'total_credit' => round($totalCredit, 3),
            'balance' => round($endingBalance, 3),
        ];

        // Template & Layout
        $template = \App\Models\Setting::where('key', 'print_template_accounting')->first();
        $templateUrl = $template?->value ? \Illuminate\Support\Facades\Storage::url($template->value) : null;
        $layoutSetting = \App\Models\Setting::where('key', 'print_layout_statement')->first();
        $layout = $layoutSetting?->value ? json_decode($layoutSetting->value, true) : null;

        return Inertia::render('Clients/PrintStatement', [
            'client' => $client,
            'entries' => $entries,
            'summary' => $summary,
            'filters' => ['from' => $from, 'to' => $to],
            'templateUrl' => $templateUrl,
            'layout' => $layout,
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
            'contact_person' => 'nullable|string|max:255',
            'id_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'credit_limit_jod' => 'nullable|numeric|min:0',
        ]);

        $lastCode = Client::withTrashed()->where('code', 'like', 'CLT-%')->orderByDesc('code')->value('code');
        $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
        $validated['code'] = 'CLT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $validated['currency'] = $validated['country'] === 'JO' ? 'JOD' : 'SAR';
        $validated['balance_jod'] = 0;
        $validated['is_active'] = true;
        $validated['credit_limit_jod'] = $validated['credit_limit_jod'] ?? 0;

        $client = Client::create($validated);

        // حساب محاسبي يُنشأ تلقائياً عبر ClientObserver

        return redirect()->route('clients.index')
            ->with('success', 'تم إضافة العميل بنجاح');
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'country' => 'required|in:JO,SA',
            'city' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'id_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'credit_limit_jod' => 'nullable|numeric|min:0',
        ]);

        $validated['currency'] = $validated['country'] === 'JO' ? 'JOD' : 'SAR';
        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'تم تحديث بيانات العميل بنجاح');
    }

    public function destroy(Client $client)
    {
        // منع حذف عميل لديه أي عمليات مسجلة
        $hasLedger = LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)->exists();
        
        if ($hasLedger) {
            return back()->with('error', 'لا يمكن حذف عميل تم إجراء عمليات مالية عليه.');
        }

        if ($client->receipts()->count() > 0 || $client->invoices()->count() > 0 || $client->violations()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف عميل لديه عمليات مسجلة.');
        }

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'تم حذف العميل بنجاح');
    }
}
