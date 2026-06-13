<?php

namespace App\Observers;

use App\Models\JournalEntryLine;
use App\Models\Client;
use App\Models\Agent;
use App\Models\LedgerEntry;
use App\Services\AccountingSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryLineObserver
{
    public function created(JournalEntryLine $line): void
    {
        if (AccountingSync::$isSyncing) return;
        
        $entry = $line->journalEntry;
        if (!$entry) return;

        // نحن مهتمون فقط بالقيود اليدوية أو عكس القيود اليدوية
        $isManual = $entry->reference_type === 'manual';
        $isReversalOfManual = false;

        if ($entry->reference_type === 'reversal' && $entry->reversal_of) {
            $original = \App\Models\JournalEntry::where('entry_number', $entry->reversal_of)->first();
            if ($original && $original->reference_type === 'manual') {
                $isReversalOfManual = true;
            }
        }

        if (!$isManual && !$isReversalOfManual) return;

        AccountingSync::$isSyncing = true;

        try {
            DB::transaction(function () use ($line, $entry) {
                // تحقق هل الحساب مرتبط بعميل
                $client = Client::where('account_id', $line->account_id)->first();
                if ($client) {
                    $debit = (float)$line->debit;
                    $credit = (float)$line->credit;
                    $amount = $debit - $credit; // مدين يزيد الرصيد، دائن ينقصه للعميل (أصل)

                    if ($amount != 0) {
                        $client->increment('balance_jod', $amount);
                        
                        // إنشاء حركة في كشف الحساب
                        LedgerEntry::create([
                            'entry_date' => $entry->entry_date ?? now(),
                            'entity_type' => 'client',
                            'entity_id' => $client->id,
                            'transaction_type' => $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment',
                            'transaction_id' => $entry->id,
                            'debit' => $debit,
                            'credit' => $credit,
                            'balance_after' => $client->fresh()->balance_jod,
                            'currency' => 'JOD',
                            'description' => $line->description ?: $entry->description,
                            'created_by' => $entry->created_by,
                        ]);
                        
                        Log::info("[JournalEntryLineObserver] ✅ تم عكس/تعديل رصيد العميل التشغيلي {$client->name}: {$amount} JOD");
                    }
                    return;
                }

                // تحقق هل الحساب مرتبط بوكيل
                $agent = Agent::where('account_id', $line->account_id)->first();
                if ($agent) {
                    $debit = (float)$line->debit;
                    $credit = (float)$line->credit;
                    $amountJod = $credit - $debit; // دائن يزيد الرصيد، مدين ينقصه للوكيل (التزام)
                    
                    if ($amountJod != 0) {
                        // تحويل إلى الريال السعودي
                        $rate = (float)(config('accounting.sar_to_jod', 0.19));
                        $amountSar = $rate > 0 ? round($amountJod / $rate, 3) : 0;
                        
                        $agent->increment('balance_sar', $amountSar);
                        
                        // إنشاء حركة في كشف الحساب بالريال
                        LedgerEntry::create([
                            'entry_date' => $entry->entry_date ?? now(),
                            'entity_type' => 'agent',
                            'entity_id' => $agent->id,
                            'transaction_type' => $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment',
                            'transaction_id' => $entry->id,
                            'debit' => $rate > 0 ? round($debit / $rate, 3) : 0,
                            'credit' => $rate > 0 ? round($credit / $rate, 3) : 0,
                            'balance_after' => $agent->fresh()->balance_sar,
                            'currency' => 'SAR',
                            'description' => $line->description ?: $entry->description,
                            'created_by' => $entry->created_by,
                        ]);

                        Log::info("[JournalEntryLineObserver] ✅ تم عكس/تعديل رصيد الوكيل التشغيلي {$agent->name}: {$amountSar} SAR");
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error("[JournalEntryLineObserver] خطأ أثناء المزامنة: " . $e->getMessage());
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }

    public function deleted(JournalEntryLine $line): void
    {
        if (AccountingSync::$isSyncing) return;

        $entry = $line->journalEntry;
        if (!$entry) return;

        $isManual = $entry->reference_type === 'manual';
        $isReversalOfManual = false;

        if ($entry->reference_type === 'reversal' && $entry->reversal_of) {
            $original = \App\Models\JournalEntry::where('entry_number', $entry->reversal_of)->first();
            if ($original && $original->reference_type === 'manual') {
                $isReversalOfManual = true;
            }
        }

        if (!$isManual && !$isReversalOfManual) return;

        AccountingSync::$isSyncing = true;

        try {
            DB::transaction(function () use ($line, $entry) {
                // عميل
                $client = Client::where('account_id', $line->account_id)->first();
                if ($client) {
                    $debit = (float)$line->debit;
                    $credit = (float)$line->credit;
                    $amount = $debit - $credit;

                    if ($amount != 0) {
                        $client->decrement('balance_jod', $amount);
                        
                        // حذف الحركة التشغيلية المرتبطة بها
                        LedgerEntry::where('entity_type', 'client')
                            ->where('entity_id', $client->id)
                            ->where('transaction_type', $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment')
                            ->where('transaction_id', $entry->id)
                            ->delete();
                    }
                    return;
                }

                // وكيل
                $agent = Agent::where('account_id', $line->account_id)->first();
                if ($agent) {
                    $debit = (float)$line->debit;
                    $credit = (float)$line->credit;
                    $amountJod = $credit - $debit;

                    if ($amountJod != 0) {
                        $rate = (float)(config('accounting.sar_to_jod', 0.19));
                        $amountSar = $rate > 0 ? round($amountJod / $rate, 3) : 0;
                        
                        $agent->decrement('balance_sar', $amountSar);
                        
                        LedgerEntry::where('entity_type', 'agent')
                            ->where('entity_id', $agent->id)
                            ->where('transaction_type', $entry->reference_type === 'reversal' ? 'reversal' : 'adjustment')
                            ->where('transaction_id', $entry->id)
                            ->delete();
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error("[JournalEntryLineObserver] خطأ أثناء حذف المزامنة: " . $e->getMessage());
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }
}
