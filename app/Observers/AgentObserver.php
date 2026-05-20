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
            // الوكلاء يندرجون تحت 2110 الوكلاء (فرع من دائنون متنوعون)
            $parentAccount = Account::where('code', '2110')->first();
            
            if ($parentAccount) {
                if (!$agent->account_id) {
                    // إنشاء حساب فرعي جديد للوكيل
                    $nextCode = Account::where('parent_id', $parentAccount->id)
                        ->max('code');
                    $newCode = $nextCode ? strval(intval($nextCode) + 1) : '2111';

                    $account = Account::create([
                        'code' => $newCode,
                        'name' => $agent->name,
                        'parent_id' => $parentAccount->id,
                        'type' => 'liability',  // التزام (دائن)
                        'is_active' => $agent->is_active,
                        'currency' => 'JOD',
                    ]);

                    $agent->update(['account_id' => $account->id]);
                } else {
                    // تحديث الحساب الموجود
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
