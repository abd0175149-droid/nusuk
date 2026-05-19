<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\Account;
use App\Services\AccountingSync;
use Illuminate\Support\Str;

class AgentObserver
{
    public function saved(Agent $agent): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            $parentAccount = Account::where('code', '1300')->first();
            
            if ($parentAccount) {
                if (!$agent->account_id) {
                    // Create Account
                    $nextCode = Account::where('parent_id', $parentAccount->id)
                        ->max('code');
                    $newCode = $nextCode ? strval(intval($nextCode) + 1) : '1301';

                    $account = Account::create([
                        'code' => $newCode,
                        'name' => $agent->name,
                        'parent_id' => $parentAccount->id,
                        'type' => 'asset',
                        'is_active' => $agent->is_active,
                        'currency' => 'JOD',
                    ]);

                    $agent->update(['account_id' => $account->id]);
                } else {
                    // Update existing Account
                    Account::where('id', $agent->account_id)->update([
                        'name' => $agent->name,
                        'is_active' => $agent->is_active,
                    ]);
                }
            }
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }

    public function deleted(Agent $agent): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            if ($agent->account_id) {
                Account::where('id', $agent->account_id)->delete();
            }
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }
}
