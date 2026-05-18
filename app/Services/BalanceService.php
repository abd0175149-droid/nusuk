<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Client;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * زيادة رصيد الوكيل (حوالات واردة)
     */
    public static function creditAgent(Agent $agent, float $amount, string $type, int $transactionId): LedgerEntry
    {
        return DB::transaction(function () use ($agent, $amount, $type, $transactionId) {
            $agent->increment('balance_sar', $amount);

            return LedgerEntry::create([
                'entry_date' => now(),
                'entity_type' => 'agent',
                'entity_id' => $agent->id,
                'debit' => 0,
                'credit' => $amount,
                'balance_after' => $agent->fresh()->balance_sar,
                'currency' => 'SAR',
                'transaction_type' => $type,
                'transaction_id' => $transactionId,
                'description' => "إضافة {$amount} SAR - {$type} #{$transactionId}",
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * خصم من رصيد الوكيل (مخالفات / فواتير)
     */
    public static function debitAgent(Agent $agent, float $amount, string $type, int $transactionId): LedgerEntry
    {
        return DB::transaction(function () use ($agent, $amount, $type, $transactionId) {
            $agent->decrement('balance_sar', $amount);

            return LedgerEntry::create([
                'entry_date' => now(),
                'entity_type' => 'agent',
                'entity_id' => $agent->id,
                'debit' => $amount,
                'credit' => 0,
                'balance_after' => $agent->fresh()->balance_sar,
                'currency' => 'SAR',
                'transaction_type' => $type,
                'transaction_id' => $transactionId,
                'description' => "خصم {$amount} SAR - {$type} #{$transactionId}",
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * زيادة ذمة العميل (فواتير)
     */
    public static function debitClient(Client $client, float $amount, string $type, int $transactionId): LedgerEntry
    {
        return DB::transaction(function () use ($client, $amount, $type, $transactionId) {
            $client->increment('balance_jod', $amount);

            return LedgerEntry::create([
                'entry_date' => now(),
                'entity_type' => 'client',
                'entity_id' => $client->id,
                'debit' => $amount,
                'credit' => 0,
                'balance_after' => $client->fresh()->balance_jod,
                'currency' => 'JOD',
                'transaction_type' => $type,
                'transaction_id' => $transactionId,
                'description' => "إضافة ذمة {$amount} JOD - {$type} #{$transactionId}",
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * تسديد ذمة العميل (سندات قبض)
     */
    public static function creditClient(Client $client, float $amount, string $type, int $transactionId): LedgerEntry
    {
        return DB::transaction(function () use ($client, $amount, $type, $transactionId) {
            $client->decrement('balance_jod', $amount);

            return LedgerEntry::create([
                'entry_date' => now(),
                'entity_type' => 'client',
                'entity_id' => $client->id,
                'debit' => 0,
                'credit' => $amount,
                'balance_after' => $client->fresh()->balance_jod,
                'currency' => 'JOD',
                'transaction_type' => $type,
                'transaction_id' => $transactionId,
                'description' => "تسديد {$amount} JOD - {$type} #{$transactionId}",
                'created_by' => auth()->id(),
            ]);
        });
    }
}
