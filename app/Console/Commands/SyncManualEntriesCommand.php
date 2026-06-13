<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Agent;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\LedgerEntry;
use App\Services\AccountingSync;
use Illuminate\Support\Facades\DB;

class SyncManualEntriesCommand extends Command
{
    protected $signature = 'accounting:sync-manual-entries';
    protected $description = 'مزامنة أسطر القيود اليدوية وعكسها مع كشوف الحساب التشغيلية للعملاء والوكلاء وإعادة احتساب الأرصدة';

    public function handle()
    {
        $this->info('=== بدء عملية مطابقة ومزامنة القيود اليدوية ===');

        // تعطيل المزامنة التلقائية أثناء تشغيل الأمر لمنع التداخل وحلقات التكرار
        AccountingSync::$isSyncing = true;

        try {
            DB::transaction(function () {
                $syncedCount = 0;

                // 1. جلب القيود اليدوية وعكسها
                $entries = JournalEntry::where(function ($query) {
                    $query->where('reference_type', 'manual')
                          ->orWhere('reference_type', 'reversal');
                })->get();

                foreach ($entries as $entry) {
                    // إذا كان قيد عكسي، نتحقق أن القيد الأصلي كان يدوياً
                    if ($entry->reference_type === 'reversal' && $entry->reversal_of) {
                        $original = JournalEntry::where('entry_number', $entry->reversal_of)->first();
                        if (!$original || $original->reference_type !== 'manual') {
                            continue; // تخطي القيد إذا كان عكس عملية نظام مؤتمتة (مثل تعديل فاتورة)
                        }
                    }

                    $entry->load('lines');

                    foreach ($entry->lines as $line) {
                        // تحقق من العملاء
                        $client = Client::where('account_id', $line->account_id)->first();
                        if ($client) {
                            $debit = (float)$line->debit;
                            $credit = (float)$line->credit;
                            $amount = $debit - $credit;

                            if ($amount != 0) {
                                // تحقق هل الحركة مسجلة مسبقاً في LedgerEntry
                                $exists = LedgerEntry::where('entity_type', 'client')
                                    ->where('entity_id', $client->id)
                                    ->where('transaction_id', $entry->id)
                                    ->where('transaction_type', $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment')
                                    ->where('debit', $debit)
                                    ->where('credit', $credit)
                                    ->exists();

                                if (!$exists) {
                                    LedgerEntry::create([
                                        'entry_date' => $entry->entry_date,
                                        'entity_type' => 'client',
                                        'entity_id' => $client->id,
                                        'transaction_type' => $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment',
                                        'transaction_id' => $entry->id,
                                        'debit' => $debit,
                                        'credit' => $credit,
                                        'balance_after' => 0.000, // سيتم تحديثها لاحقاً عند إعادة احتساب الرصيد المجمع
                                        'currency' => 'JOD',
                                        'description' => $line->description ?: $entry->description,
                                        'created_by' => $entry->created_by,
                                    ]);

                                    $this->line("✅ تم مزامنة حركة قيد يدوي للعميل: {$client->name} بقيمة " . abs($amount) . " JOD (قيد رقم: {$entry->entry_number})");
                                    $syncedCount++;
                                }
                            }
                            continue;
                        }

                        // تحقق من الوكلاء
                        $agent = Agent::where('account_id', $line->account_id)->first();
                        if ($agent) {
                            $debit = (float)$line->debit;
                            $credit = (float)$line->credit;
                            $amountJod = $credit - $debit;

                            if ($amountJod != 0) {
                                $exists = LedgerEntry::where('entity_type', 'agent')
                                    ->where('entity_id', $agent->id)
                                    ->where('transaction_id', $entry->id)
                                    ->where('transaction_type', $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment')
                                    ->exists();

                                if (!$exists) {
                                    $rate = (float)(config('accounting.sar_to_jod', 0.19));
                                    
                                    LedgerEntry::create([
                                        'entry_date' => $entry->entry_date,
                                        'entity_type' => 'agent',
                                        'entity_id' => $agent->id,
                                        'transaction_type' => $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment',
                                        'transaction_id' => $entry->id,
                                        'debit' => $rate > 0 ? round($debit / $rate, 3) : 0,
                                        'credit' => $rate > 0 ? round($credit / $rate, 3) : 0,
                                        'balance_after' => 0.000, // سيتم تحديثها لاحقاً
                                        'currency' => 'SAR',
                                        'description' => $line->description ?: $entry->description,
                                        'created_by' => $entry->created_by,
                                    ]);

                                    $this->line("✅ تم مزامنة حركة قيد يدوي للوكيل: {$agent->name} بقيمة " . abs($amountJod) . " JOD (قيد رقم: {$entry->entry_number})");
                                    $syncedCount++;
                                }
                            }
                        }
                    }
                }

                $this->info("🔄 تم إدراج {$syncedCount} حركة مفقودة بنجاح.");
                $this->info('=== إعادة احتساب وتحديث الأرصدة التراكمية في كشوفات الحساب ===');

                // 2. تحديث الرصيد التراكمي (balance_after) في كشوفات حساب العملاء
                $clients = Client::all();
                foreach ($clients as $client) {
                    $entries = LedgerEntry::where('entity_type', 'client')
                        ->where('entity_id', $client->id)
                        ->orderBy('entry_date')
                        ->orderBy('id')
                        ->get();

                    $runningBalance = 0.000;
                    foreach ($entries as $ledgerEntry) {
                        $runningBalance += ((float)$ledgerEntry->debit - (float)$ledgerEntry->credit);
                        $ledgerEntry->update(['balance_after' => $runningBalance]);
                    }

                    // تحديث الرصيد النهائي في جدول العملاء
                    $client->update(['balance_jod' => $runningBalance]);
                    $this->line("🔹 تم تحديث الرصيد النهائي للعميل [{$client->name}] ليصبح: {$runningBalance} JOD");
                }

                // 3. تحديث الرصيد التراكمي (balance_after) في كشوفات حساب الوكلاء
                $agents = Agent::all();
                foreach ($agents as $agent) {
                    $entries = LedgerEntry::where('entity_type', 'agent')
                        ->where('entity_id', $agent->id)
                        ->orderBy('entry_date')
                        ->orderBy('id')
                        ->get();

                    $runningBalance = 0.000;
                    foreach ($entries as $ledgerEntry) {
                        $runningBalance += ((float)$ledgerEntry->credit - (float)$ledgerEntry->debit);
                        $ledgerEntry->update(['balance_after' => $runningBalance]);
                    }

                    // تحديث الرصيد النهائي في جدول الوكلاء
                    $agent->update(['balance_sar' => $runningBalance]);
                    $this->line("🔹 تم تحديث الرصيد النهائي للوكيل [{$agent->name}] ليصبح: {$runningBalance} SAR");
                }
            });

            $this->info('✅ تم الانتهاء من مطابقة الأرصدة وتصحيح كشوف الحسابات بنجاح!');
        } catch (\Throwable $e) {
            $this->error('❌ حدث خطأ أثناء المزامنة: ' . $e->getMessage());
        } finally {
            AccountingSync::$isSyncing = false;
        }

        return 0;
    }
}
