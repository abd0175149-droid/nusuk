<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\LedgerEntry;

try {
    // 1. جلب العميل ورصيده التشغيلي
    $client = Client::find(1);
    if (!$client) {
        echo "⚠️ لم يتم العثور على العميل رقم 1" . PHP_EOL;
        exit(1);
    }
    
    echo "=== بيانات العميل في الطبقة التشغيلية ===" . PHP_EOL;
    echo "الاسم: " . $client->name . PHP_EOL;
    echo "الرصيد التشغيلي الحالي (balance_jod): " . $client->balance_jod . " JOD" . PHP_EOL;
    echo "معرّف الحساب المحاسبي المرتبط (account_id): " . $client->account_id . PHP_EOL;

    // 2. جلب الحساب المحاسبي المرتبط ورصيده
    $account = Account::find($client->account_id);
    if ($account) {
        echo PHP_EOL . "=== بيانات الحساب في الطبقة المحاسبية (GL) ===" . PHP_EOL;
        echo "رقم الحساب: " . $account->code . PHP_EOL;
        echo "اسم الحساب: " . $account->name . PHP_EOL;
        echo "الرصيد المحاسبي المخزن بالحساب (balance): " . $account->balance . " JOD" . PHP_EOL;

        // حساب الرصيد الفعلي من قيود اليومية مباشرة (مدين - دائن)
        $linesSum = JournalLine::where('account_id', $account->id)
            ->selectRaw("SUM(debit) as total_debit, SUM(credit) as total_credit")
            ->first();
        $calculatedBalance = ($linesSum->total_debit ?? 0) - ($linesSum->total_credit ?? 0);
        echo "مجموع مدين قيود اليومية: " . ($linesSum->total_debit ?? 0) . " JOD" . PHP_EOL;
        echo "مجموع دائن قيود اليومية: " . ($linesSum->total_credit ?? 0) . " JOD" . PHP_EOL;
        echo "الرصيد المحسوب من دفتر اليومية (مدين - دائن): " . $calculatedBalance . " JOD" . PHP_EOL;
    } else {
        echo PHP_EOL . "⚠️ تحذير: لا يوجد حساب محاسبي مرتبط بالعميل!" . PHP_EOL;
    }

    // 3. مقارنة عدد الحركات المسجلة في كل طبقة
    echo PHP_EOL . "=== مقارنة الحركات بين الطبقتين ===" . PHP_EOL;
    $ledgerEntriesCount = LedgerEntry::where('entity_type', 'client')->where('entity_id', $client->id)->count();
    $journalLinesCount = $account ? JournalLine::where('account_id', $account->id)->count() : 0;
    echo "عدد الحركات في كشف الحساب التشغيلي (LedgerEntry): " . $ledgerEntriesCount . PHP_EOL;
    echo "عدد الحركات في دفتر اليومية المحاسبي (JournalLine): " . $journalLinesCount . PHP_EOL;

    // 4. استخراج القيود اليدوية التي سببت الفجوة
    if ($account) {
        $manualLines = JournalLine::where('account_id', $account->id)
            ->whereHas('entry', function($q) {
                $q->where('reference_type', 'manual');
            })
            ->with('entry')
            ->get();
            
        echo PHP_EOL . "=== القيود اليدوية المحاسبية غير المنعكسة في كشف الحساب التشغيلي ===" . PHP_EOL;
        if ($manualLines->isEmpty()) {
            echo "لا توجد قيود يدوية محاسبية لهذا العميل." . PHP_EOL;
        } else {
            foreach ($manualLines as $line) {
                $desc = $line->description ?: ($line->entry ? $line->entry->description : 'بدون بيان');
                echo "رقم القيد: " . ($line->entry ? $line->entry->entry_number : 'N/A') . 
                     " | التاريخ: " . ($line->entry ? $line->entry->entry_date : 'N/A') . 
                     " | مدين: " . $line->debit . 
                     " | دائن: " . $line->credit . 
                     " | البيان: " . $desc . PHP_EOL;
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ حدث خطأ أثناء التحليل: " . $e->getMessage() . PHP_EOL;
}
