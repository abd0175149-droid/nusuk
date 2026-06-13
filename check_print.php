<?php
// Script to clear OPcache and check Inertia response
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared.\n";
}

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/clients/1/print-statement', 'GET', ['from' => '2026-06-01', 'to' => '2026-06-30']);
$request->headers->set('X-Inertia', 'true');
$controller = app(\App\Http\Controllers\ClientController::class);
$response = $controller->printStatement($request, \App\Models\Client::find(1));

$data = $response->toResponse($request)->getData(true);
$entries = $data['page']['props']['entries'] ?? [];

echo "Found " . count($entries) . " entries in PrintStatement response.\n";
foreach($entries as $e) {
    echo $e['entry_date'] . " | " . $e['transaction_type'] . " | " . $e['description'] . "\n";
}
