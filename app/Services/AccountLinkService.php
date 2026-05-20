<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;

/**
 * خدمة ربط الوكلاء والعملاء بحساباتهم المحاسبية
 */
class AccountLinkService
{
    /**
     * إنشاء حساب فرعي لوكيل جديد تحت "دائنون متنوعون" (2101)
     */
    public static function createAgentAccount(Agent $agent): Account
    {
        $parent = Account::where('code', '2101')->first();
        if (!$parent) {
            throw new \Exception('حساب دائنون متنوعون (2101) غير موجود');
        }

        // توليد الكود التالي
        $lastChild = Account::where('parent_id', $parent->id)
            ->orderByDesc('code')->value('code');
        $nextCode = $lastChild
            ? (string) (intval($lastChild) + 1)
            : '2111';

        $account = Account::create([
            'code' => $nextCode,
            'name' => "وكيل: {$agent->name}",
            'type' => 'liability',
            'parent_id' => $parent->id,
            'is_system' => false,
            'is_active' => true,
            'balance' => 0,
            'currency' => 'JOD',
            'description' => "حساب الوكيل {$agent->name} ({$agent->code})",
        ]);

        $agent->update(['account_id' => $account->id]);

        return $account;
    }

    /**
     * إنشاء حساب فرعي لعميل جديد تحت "ذمم العملاء" (1200)
     */
    public static function createClientAccount(Client $client): Account
    {
        $parent = Account::where('code', '1200')->first();
        if (!$parent) {
            throw new \Exception('حساب ذمم العملاء (1200) غير موجود');
        }

        $lastChild = Account::where('parent_id', $parent->id)
            ->orderByDesc('code')->value('code');
        $nextCode = $lastChild
            ? (string) (intval($lastChild) + 1)
            : (string) (intval($parent->code) + 1);

        $account = Account::create([
            'code' => $nextCode,
            'name' => "عميل: {$client->name}",
            'type' => 'asset',
            'parent_id' => $parent->id,
            'is_system' => false,
            'is_active' => true,
            'balance' => 0,
            'currency' => 'JOD',
            'description' => "حساب العميل {$client->name} ({$client->code})",
        ]);

        $client->update(['account_id' => $account->id]);

        return $account;
    }

    /**
     * إنشاء حسابات لجميع الوكلاء/العملاء الحاليين الذين ليس لديهم حسابات
     * يُستخدم مرة واحدة لترحيل البيانات الموجودة
     */
    public static function linkExistingEntities(): array
    {
        $stats = ['agents' => 0, 'clients' => 0];

        $agents = Agent::whereNull('account_id')->get();
        foreach ($agents as $agent) {
            try {
                self::createAgentAccount($agent);
                $stats['agents']++;
            } catch (\Exception $e) {}
        }

        $clients = Client::whereNull('account_id')->get();
        foreach ($clients as $client) {
            try {
                self::createClientAccount($client);
                $stats['clients']++;
            } catch (\Exception $e) {}
        }

        return $stats;
    }
}
