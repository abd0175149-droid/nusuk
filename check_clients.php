<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
use App\Models\Account;

echo "--- Clients count ---\n";
echo "Total clients: " . Client::count() . "\n";
echo "Clients list:\n";
foreach (Client::orderBy('code')->get() as $c) {
    echo "Client: ID={$c->id}, Code={$c->code}, Name={$c->name}, AccountID={$c->account_id}\n";
}

echo "\n--- Accounts list under 1200 ---\n";
$parent = Account::where('code', '1200')->first();
if ($parent) {
    echo "Parent account 1200 found: ID={$parent->id}, Name={$parent->name}\n";
    $children = Account::where('parent_id', $parent->id)->orderBy('code')->get();
    echo "Children count: " . $children->count() . "\n";
    foreach ($children as $child) {
        echo "  Child Account: ID={$child->id}, Code={$child->code}, Name={$child->name}\n";
    }
} else {
    echo "Parent account 1200 not found!\n";
}

echo "\n--- Last Child Query Check ---\n";
if ($parent) {
    $lastChild = Account::where('parent_id', $parent->id)
        ->where('code', 'like', '1200%')
        ->orderByDesc('code')
        ->first();
    if ($lastChild) {
        echo "orderByDesc('code') returns: Code={$lastChild->code}, Name={$lastChild->name}\n";
    } else {
        echo "No children found.\n";
    }
}
