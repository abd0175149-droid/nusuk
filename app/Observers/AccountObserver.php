<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Service;
use App\Models\ExpenseCategory;
use App\Models\ViolationType;
use App\Services\AccountingSync;

class AccountObserver
{
    public function saved(Account $account): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            if (!$account->parent_id) return;
            
            $parent = Account::find($account->parent_id);
            if (!$parent) return;

            $entityData = [
                'name' => $account->name,
                'account_id' => $account->id,
                'is_active' => $account->is_active ?? true,
            ];

            if ($parent->code === '2101') {
                // Agent
                $agent = Agent::where('account_id', $account->id)->first();
                if (!$agent) {
                    $lastCode = Agent::where('code', 'like', 'AGT-%')->orderByDesc('code')->value('code');
                    $nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
                    $entityData['code'] = 'AGT-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    $entityData['country'] = 'JO';
                    $entityData['currency'] = 'JOD';
                    $entityData['balance_sar'] = 0;
                    Agent::create($entityData);
                } else {
                    $agent->update(['name' => $account->name, 'is_active' => $account->is_active]);
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
                    Client::create($entityData);
                } else {
                    $client->update(['name' => $account->name, 'is_active' => $account->is_active]);
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
                    Service::create($entityData);
                } else {
                    $service->update(['name' => $account->name, 'is_active' => $account->is_active]);
                }
            } elseif ($parent->code === '5100') {
                // Expense Category
                $category = ExpenseCategory::where('account_id', $account->id)->first();
                if (!$category) {
                    $entityData['code'] = 'EXP-' . rand(100, 999);
                    ExpenseCategory::create($entityData);
                } else {
                    $category->update(['name' => $account->name, 'is_active' => $account->is_active]);
                }
            } elseif ($parent->code === '5200') {
                // Violation Type
                $type = ViolationType::where('account_id', $account->id)->first();
                if (!$type) {
                    $entityData['code'] = 'VIO-' . rand(100, 999);
                    ViolationType::create($entityData);
                } else {
                    $type->update(['name' => $account->name, 'is_active' => $account->is_active]);
                }
            }
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }

    public function deleted(Account $account): void
    {
        if (AccountingSync::$isSyncing) return;
        AccountingSync::$isSyncing = true;

        try {
            Agent::where('account_id', $account->id)->delete();
            Client::where('account_id', $account->id)->delete();
            Service::where('account_id', $account->id)->delete();
            ExpenseCategory::where('account_id', $account->id)->delete();
            ViolationType::where('account_id', $account->id)->delete();
        } finally {
            AccountingSync::$isSyncing = false;
        }
    }
}
