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
        $from = $request->from ?: null;
        $to = $request->to ?: null;

        // 1. الفواتير المعتمدة + القيود المدينة
        $invoicesQuery = \App\Models\Invoice::where('client_id', $client->id)
            ->where('status', 'approved');
        if ($from && $to) {
            $invoicesQuery->whereBetween('invoice_date', [$from, $to . ' 23:59:59']);
        }
        $invoices = $invoicesQuery->with(['items.violation.violationType'])
            ->get()
            ->map(function ($inv) {
                $services = $inv->items->map(function ($item) {
                    if ($item->item_type === 'violation' && $item->violation) {
                        $v = $item->violation;
                        $typeName = $v->violationType?->name ?? 'مخالفة';
                        $passport = $v->passport_name ? " ({$v->passport_name})" : '';
                        return [
                            'name' => "{$typeName}{$passport}",
                            'qty' => 1,
                            'price' => round($v->cost_sar, 3) . ' SAR',
                        ];
                    }
                    return [
                        'name' => $item->description,
                        'qty' => $item->quantity,
                        'price' => round($item->sell_price_jod, 3) . ' JOD',
                    ];
                })->toArray();

                return [
                    'id' => 'INV-' . $inv->id,
                    'date' => $inv->invoice_date->format('Y-m-d'),
                    'type' => 'فاتورة',
                    'reference' => $inv->invoice_number,
                    'services' => count($services) > 0 ? $services : [['name' => 'بدون تفاصيل', 'qty' => '-', 'price' => '-']],
                    'amount' => round($inv->total_jod, 3),
                ];
            });

        $manualDebitsQuery = \App\Models\LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->where('transaction_type', 'journal')
            ->where('debit', '>', 0);
        if ($from && $to) {
            $manualDebitsQuery->whereBetween('entry_date', [$from, $to . ' 23:59:59']);
        }
        $manualDebits = $manualDebitsQuery->get()->map(function ($entry) {
            return [
                'id' => 'JRN-' . $entry->id,
                'date' => $entry->entry_date->format('Y-m-d'),
                'type' => 'قيد مدين',
                'reference' => 'JRN-' . $entry->transaction_id,
                'services' => [['name' => $entry->description ?: 'قيد تسوية', 'qty' => '-', 'price' => '-']],
                'amount' => round($entry->debit, 3),
            ];
        });

        $charges = $invoices->concat($manualDebits)->sortBy('date')->values();

        // 2. سندات القبض المعتمدة + القيود الدائنة
        $receiptsQuery = \App\Models\Receipt::where('client_id', $client->id)
            ->where('status', 'approved');
        if ($from && $to) {
            $receiptsQuery->whereBetween('receipt_date', [$from, $to . ' 23:59:59']);
        }
        $receipts = $receiptsQuery->get()->map(function ($r) {
            $paymentMethod = match ($r->payment_method) {
                'cash' => 'نقداً',
                'bank' => 'تحويل بنكي',
                'check' => 'شيك',
                default => $r->payment_method,
            };
            return [
                'id' => 'REC-' . $r->id,
                'date' => $r->receipt_date->format('Y-m-d'),
                'type' => 'سند قبض',
                'reference' => $r->receipt_number,
                'services' => [['name' => "دفع {$paymentMethod}" . ($r->notes ? ' - ' . $r->notes : ''), 'qty' => '-', 'price' => '-']],
                'amount' => round($r->amount_jod, 3),
            ];
        });

        $manualCreditsQuery = \App\Models\LedgerEntry::where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->where('transaction_type', 'journal')
            ->where('credit', '>', 0);
        if ($from && $to) {
            $manualCreditsQuery->whereBetween('entry_date', [$from, $to . ' 23:59:59']);
        }
        $manualCredits = $manualCreditsQuery->get()->map(function ($entry) {
            return [
                'id' => 'JRN-' . $entry->id,
                'date' => $entry->entry_date->format('Y-m-d'),
                'type' => 'قيد دائن',
                'reference' => 'JRN-' . $entry->transaction_id,
                'services' => [['name' => $entry->description ?: 'قيد تسوية', 'qty' => '-', 'price' => '-']],
                'amount' => round($entry->credit, 3),
            ];
        });

        $payments = $receipts->concat($manualCredits)->sortBy('date')->values();

        // 3. الملخص والرصيد الافتتاحي
        $openingBalance = 0;
        if ($from) {
            $openingBalance = \App\Models\LedgerEntry::where('entity_type', 'client')
                ->where('entity_id', $client->id)
                ->where('entry_date', '<', $from)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;
        }

        $totalCharges = $charges->sum('amount');
        $totalPayments = $payments->sum('amount');
        $balance = $openingBalance + $totalCharges - $totalPayments;

        $summary = [
            'opening_balance' => round($openingBalance, 3),
            'charges_total' => round($totalCharges, 3),
            'payments_total' => round($totalPayments, 3),
            'balance' => round($balance, 3),
        ];

        // Template & Layout
        $template = \App\Models\Setting::where('key', 'print_template_accounting')->first();
        $templateUrl = $template?->value ? \Illuminate\Support\Facades\Storage::url($template->value) : null;
        $layoutSetting = \App\Models\Setting::where('key', 'print_layout_statement')->first();
        $layout = $layoutSetting?->value ? json_decode($layoutSetting->value, true) : null;

        return Inertia::render('Clients/PrintStatement', [
            'client' => $client,
            'charges' => $charges,
            'payments' => $payments,
            'summary' => $summary,
            'filters' => ['from' => $from ?? 'الكل', 'to' => $to ?? 'الكل'],
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
