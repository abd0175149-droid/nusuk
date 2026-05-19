<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ViolationTypeController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// Auth Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Agents & Clients CRUD
    Route::resource('agents', AgentController::class);
    Route::resource('clients', ClientController::class);

    // Transfers
    Route::resource('transfers', TransferController::class)->only(['index', 'store', 'destroy']);
    Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve'])->name('transfers.approve');
    Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');

    // Receipts
    Route::resource('receipts', ReceiptController::class)->only(['index', 'store', 'destroy']);
    Route::post('receipts/{receipt}/approve', [ReceiptController::class, 'approve'])->name('receipts.approve');
    Route::post('receipts/{receipt}/reject', [ReceiptController::class, 'reject'])->name('receipts.reject');

    // Expenses
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy']);
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');

    // Services
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);

    // Violation Types
    Route::resource('violation-types', ViolationTypeController::class)->only(['index', 'store', 'update', 'destroy']);

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/exchange-rate', [SettingController::class, 'storeExchangeRate'])->name('settings.exchange-rate');

    // Violations
    Route::resource('violations', \App\Http\Controllers\ViolationController::class)->only(['index', 'store', 'destroy']);
    Route::post('violations/{violation}/approve', [\App\Http\Controllers\ViolationController::class, 'approve'])->name('violations.approve');
    Route::post('violations/{violation}/reject', [\App\Http\Controllers\ViolationController::class, 'reject'])->name('violations.reject');

    // Invoices
    Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)->only(['index', 'store', 'destroy']);
    Route::post('invoices/{invoice}/approve', [\App\Http\Controllers\InvoiceController::class, 'approve'])->name('invoices.approve');
    Route::post('invoices/{invoice}/reject', [\App\Http\Controllers\InvoiceController::class, 'reject'])->name('invoices.reject');

    // API: Unbilled violations for client
    Route::get('api/clients/{client}/violations/unbilled', [\App\Http\Controllers\InvoiceController::class, 'unbilledViolations']);

    // API: FCM Tokens
    Route::post('api/fcm-token', [\App\Http\Controllers\FcmTokenController::class, 'store']);
    Route::delete('api/fcm-token', [\App\Http\Controllers\FcmTokenController::class, 'destroy']);

    // API: Notifications
    Route::get('api/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('api/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('api/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // Expense Categories
    Route::resource('expense-categories', \App\Http\Controllers\ExpenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    // Reports
    Route::get('reports/agents-balances', [\App\Http\Controllers\ReportController::class, 'agentsBalances'])->name('reports.agents-balances');
    Route::get('reports/clients-balances', [\App\Http\Controllers\ReportController::class, 'clientsBalances'])->name('reports.clients-balances');
    Route::get('reports/profit-loss', fn () => redirect('/accounting/profit-loss'));
    Route::get('reports/daily-summary', [\App\Http\Controllers\ReportController::class, 'dailySummary'])->name('reports.daily-summary');

    // Accounting
    Route::get('accounting/chart-of-accounts', [\App\Http\Controllers\AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
    Route::get('accounting/chart-of-accounts/print', [\App\Http\Controllers\AccountingController::class, 'printChart'])->name('accounting.chart.print');
    Route::post('accounting/accounts', [\App\Http\Controllers\AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
    Route::get('accounting/accounts/next-code', [\App\Http\Controllers\AccountingController::class, 'nextCode'])->name('accounting.accounts.next-code');
    Route::put('accounting/accounts/{account}', [\App\Http\Controllers\AccountingController::class, 'updateAccount'])->name('accounting.accounts.update');
    Route::delete('accounting/accounts/{account}', [\App\Http\Controllers\AccountingController::class, 'destroyAccount'])->name('accounting.accounts.destroy');
    Route::get('accounting/trial-balance', [\App\Http\Controllers\AccountingController::class, 'trialBalance'])->name('accounting.trial-balance');
    Route::get('accounting/journal-entries', [\App\Http\Controllers\AccountingController::class, 'journalEntries'])->name('accounting.journal-entries');
    Route::post('accounting/journal-entries', [\App\Http\Controllers\AccountingController::class, 'storeJournal'])->name('accounting.journal-entries.store');
    Route::post('accounting/journal-entries/{entry}/reverse', [\App\Http\Controllers\AccountingController::class, 'reverseEntry'])->name('accounting.journal-entries.reverse');
    Route::get('accounting/periods', [\App\Http\Controllers\AccountingController::class, 'periods'])->name('accounting.periods');
    Route::post('accounting/periods/close', [\App\Http\Controllers\AccountingController::class, 'closePeriod'])->name('accounting.periods.close');
    Route::post('accounting/periods/open', [\App\Http\Controllers\AccountingController::class, 'openPeriod'])->name('accounting.periods.open');
    Route::post('accounting/close-year', [\App\Http\Controllers\AccountingController::class, 'closeYear'])->name('accounting.close-year');
    Route::post('accounting/fiscal-years', [\App\Http\Controllers\AccountingController::class, 'storeFiscalYear'])->name('accounting.fiscal-years.store');
    Route::delete('accounting/fiscal-years/{fiscalYear}', [\App\Http\Controllers\AccountingController::class, 'destroyFiscalYear'])->name('accounting.fiscal-years.destroy');
    Route::get('accounting/profit-loss', [\App\Http\Controllers\AccountingController::class, 'profitAndLoss'])->name('accounting.profit-loss');
    Route::get('accounting/balance-sheet', [\App\Http\Controllers\AccountingController::class, 'balanceSheet'])->name('accounting.balance-sheet');

    // Print Pages
    Route::get('invoices/{invoice}/print', [\App\Http\Controllers\InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('agents/{agent}/print-statement', [AgentController::class, 'printStatement'])->name('agents.print-statement');
    Route::get('clients/{client}/print-statement', [ClientController::class, 'printStatement'])->name('clients.print-statement');
    Route::get('receipts/{receipt}/print', [ReceiptController::class, 'print'])->name('receipts.print');
    Route::get('expenses/{expense}/print', [ExpenseController::class, 'print'])->name('expenses.print');

    // Users & Roles
    Route::resource('users', \App\Http\Controllers\UserController::class)->except(['create', 'show', 'edit']);
    Route::resource('roles', \App\Http\Controllers\RoleController::class)->except(['create', 'show']);
});
