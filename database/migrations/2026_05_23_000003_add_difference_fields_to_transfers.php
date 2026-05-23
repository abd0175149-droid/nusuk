<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('difference_amount', 15, 3)->default(0)->after('exchange_rate')
                ->comment('الفرق بالدينار: cost_jod - (amount_sar / 0.19)');
            $table->string('difference_type', 20)->nullable()->after('difference_amount')
                ->comment('expense أو revenue');
            $table->unsignedBigInteger('expense_id')->nullable()->after('difference_type');
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('expense_id');
            $table->unsignedBigInteger('revenue_account_id')->nullable()->after('expense_category_id')
                ->comment('حساب الإيراد من شجرة الحسابات');

            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('revenue_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['transfers_expense_id_foreign']);
            $table->dropForeign(['transfers_expense_category_id_foreign']);
            $table->dropForeign(['transfers_revenue_account_id_foreign']);
            $table->dropColumn([
                'difference_amount', 'difference_type',
                'expense_id', 'expense_category_id', 'revenue_account_id',
            ]);
        });
    }
};
