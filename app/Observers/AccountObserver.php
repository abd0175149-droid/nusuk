<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Service;
use App\Models\ExpenseCategory;
use App\Models\ViolationType;
use App\Services\AccountingSync;
use Illuminate\Support\Facades\Log;

class AccountObserver
{
    public function created(Account $account): void
    {
        $this->syncEntityForAccount($account);
    }

    public function updated(Account $account): void
    {
        $this->syncEntityForAccount($account);
    }

    private function syncEntityForAccount(Account $account): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            if (!$account->parent_id) return;

            $parent = Account::find($account->parent_id);
            if (!$parent) return;

            // تحقق إذا الأب أو الجد هو 2110 (الوكلاء)
            $isAgentAccount = $parent->code === '2110';
            if (!$isAgentAccount && $parent->parent_id) {
                $grandParent = Account::find($parent->parent_id);
                $isAgentAccount = $grandParent && $grandParent->code === '2110';
            }

            $entityData = [
                'name'       => $account->name,
                'account_id' => $account->id,
                'is_active'  => $account->is_active ?? true,
            ];

            if ($isAgentAccount) {
                // Agent
                $agent = Agent::where('account_id', $account->id)->first();
                if (!$agent) {
                    $lastCode = Agent::where('code', 'like', 'AGT-%')->orderByDesc('code')->value('code');
                    $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
                    $entityData['code'] = 'AGT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    $entityData['country'] = 'JO';
                    $entityData['currency'] = 'JOD';
                    $entityData['balance_sar'] = 0;

                    Agent::withoutEvents(function () use ($entityData) {
                        Agent::create($entityData);
                    });
                    Log::info("[AccountObserver] ✅ تم إنشاء وكيل من الشجرة: {$account->name}");
                } else {
                    Agent::withoutEvents(function () use ($agent, $account) {
                        $agent->update(['name' => $account->name, 'is_active' => $account->is_active]);
                    });
                }
            } elseif ($parent->code === '1200') {
                // Client
                $client = Client::where('account_id', $account->id)->first();
                if (!$client) {
                    $lastCode = Client::where('code', 'like', 'CLT-%')->orderByDesc('code')->value('code');
                    $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
                    $entityData['code'] = 'CLT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    $entityData['country'] = 'JO';
                    $entityData['currency'] = 'JOD';
                    $entityData['balance_jod'] = 0;
                    $entityData['credit_limit_jod'] = 0;

                    Client::withoutEvents(function () use ($entityData) {
                        Client::create($entityData);
                    });
                } else {
                    Client::withoutEvents(function () use ($client, $account) {
                        $client->update(['name' => $account->name, 'is_active' => $account->is_active]);
                    });
                }
            } elseif ($parent->code === '4001') {
                // Service
                $service = Service::where('account_id', $account->id)->first();
                if (!$service) {
                    $lastCode = Service::where('code', 'like', 'SRV-%')->orderByDesc('code')->value('code');
                    $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
                    $entityData['code'] = 'SRV-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    $entityData['default_price_sar'] = 0;
                    $entityData['default_price_jod'] = 0;

                    Service::withoutEvents(function () use ($entityData) {
                        Service::create($entityData);
                    });
                } else {
                    Service::withoutEvents(function () use ($service, $account) {
                        $service->update(['name' => $account->name, 'is_active' => $account->is_active]);
                    });
                }
            } elseif ($parent->code === '5100') {
                // Expense Category
                $category = ExpenseCategory::where('account_id', $account->id)->first();
                if (!$category) {
                    $entityData['code'] = 'EXP-' . rand(100, 999);
                    ExpenseCategory::withoutEvents(function () use ($entityData) {
                        ExpenseCategory::create($entityData);
                    });
                } else {
                    ExpenseCategory::withoutEvents(function () use ($category, $account) {
                        $category->update(['name' => $account->name, 'is_active' => $account->is_active]);
                    });
                }
            } elseif ($parent->code === '5200') {
                // Violation Type
                $type = ViolationType::where('account_id', $account->id)->first();
                if (!$type) {
                    $entityData['code'] = 'VIO-' . rand(100, 999);
                    ViolationType::withoutEvents(function () use ($entityData) {
                        ViolationType::create($entityData);
                    });
                } else {
                    ViolationType::withoutEvents(function () use ($type, $account) {
                        $type->update(['name' => $account->name, 'is_active' => $account->is_active]);
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::error("[AccountObserver] خطأ: {$e->getMessage()}");
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }

    public function deleted(Account $account): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            Agent::withoutEvents(fn() => Agent::where('account_id', $account->id)->delete());
            Client::withoutEvents(fn() => Client::where('account_id', $account->id)->delete());
            Service::withoutEvents(fn() => Service::where('account_id', $account->id)->delete());
            ExpenseCategory::withoutEvents(fn() => ExpenseCategory::where('account_id', $account->id)->delete());
            ViolationType::withoutEvents(fn() => ViolationType::where('account_id', $account->id)->delete());
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }
}
