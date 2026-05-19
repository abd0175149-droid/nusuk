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

            if ($parent->code === '1300') {
                // Agent
                $agent = Agent::where('account_id', $account->id)->first();
                if (!$agent) {
                    $entityData['code'] = 'AGT-' . rand(1000, 9999);
                    Agent::create($entityData);
                } else {
                    $agent->update(['name' => $account->name, 'is_active' => $account->is_active]);
                }
            } elseif ($parent->code === '1200') {
                // Client
                $client = Client::where('account_id', $account->id)->first();
                if (!$client) {
                    $entityData['code'] = 'CLI-' . rand(1000, 9999);
                    // Requires agent_id. Since we don't know the agent, we might need a default or leave nullable if the schema allows.
                    // Assuming agent_id is required, we find the first agent. If no agent, create a default.
                    $defaultAgent = Agent::first();
                    if ($defaultAgent) {
                        $entityData['agent_id'] = $defaultAgent->id;
                        Client::create($entityData);
                    }
                } else {
                    $client->update(['name' => $account->name, 'is_active' => $account->is_active]);
                }
            } elseif ($parent->code === '4001') {
                // Service
                $service = Service::where('account_id', $account->id)->first();
                if (!$service) {
                    $entityData['code'] = 'SRV-' . rand(100, 999);
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
