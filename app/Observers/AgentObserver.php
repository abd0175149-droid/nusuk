<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\Account;
use App\Services\AccountingSync;
use Illuminate\Support\Facades\Log;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        $this->syncAccountForAgent($agent);
    }

    public function updated(Agent $agent): void
    {
        $this->syncAccountForAgent($agent);
    }

    private function syncAccountForAgent(Agent $agent): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            // الوكلاء يندرجون تحت 2110 الوكلاء (فرع من دائنون متنوعون)
            $parentAccount = Account::where('code', '2110')->first();

            // إنشاء تلقائي إذا لم يوجد
            if (!$parentAccount) {
                Log::info('[AgentObserver] حساب 2110 غير موجود — إنشاء تلقائي');
                $creditors = Account::where('code', '2101')->first();
                if (!$creditors) {
                    $liabilities = Account::where('code', '2000')->first();
                    if ($liabilities) {
                        $creditors = Account::create([
                            'code' => '2101', 'name' => 'دائنون متنوعون',
                            'type' => 'liability', 'parent_id' => $liabilities->id,
                            'is_system' => true, 'is_active' => true, 'currency' => 'JOD', 'balance' => 0,
                        ]);
                    }
                }
                if ($creditors) {
                    $parentAccount = Account::create([
                        'code' => '2110', 'name' => 'الوكلاء',
                        'type' => 'liability', 'parent_id' => $creditors->id,
                        'is_system' => true, 'is_active' => true, 'currency' => 'JOD', 'balance' => 0,
                    ]);
                }
            }

            if (!$parentAccount) {
                Log::error('[AgentObserver] فشل إنشاء حساب 2110!');
                return;
            }

            if (!$agent->account_id) {
                // إنشاء حساب فرعي جديد للوكيل
                $nextCode = Account::where('parent_id', $parentAccount->id)->max('code');
                $newCode = $nextCode ? strval(intval($nextCode) + 1) : '2111';

                $account = Account::create([
                    'code'      => $newCode,
                    'name'      => $agent->name,
                    'parent_id' => $parentAccount->id,
                    'type'      => 'liability',
                    'is_active' => $agent->is_active ?? true,
                    'is_system' => false,
                    'currency'  => 'JOD',
                    'balance'   => 0,
                ]);

                // تحديث الوكيل بدون إطلاق observer مرة أخرى
                Agent::withoutEvents(function () use ($agent, $account) {
                    $agent->update(['account_id' => $account->id]);
                });

                Log::info("[AgentObserver] ✅ تم إنشاء حساب {$newCode} للوكيل {$agent->name}");
            } else {
                // تحديث الحساب الموجود
                Account::withoutEvents(function () use ($agent) {
                    Account::where('id', $agent->account_id)->update([
                        'name'      => $agent->name,
                        'is_active' => $agent->is_active,
                    ]);
                });
            }
        } catch (\Throwable $e) {
            Log::error("[AgentObserver] خطأ: {$e->getMessage()}");
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
                Account::withoutEvents(function () use ($agent) {
                    Account::where('id', $agent->account_id)->delete();
                });
            }
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }
}
