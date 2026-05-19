<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetData extends Command
{
    protected $signature = 'app:reset-data {--confirm}';
    protected $description = 'حذف جميع البيانات ما عدا المستخدمين والصلاحيات وإعادة شجرة الحسابات';

    public function handle()
    {
        if (!$this->option('confirm')) {
            $this->error('أضف --confirm لتأكيد الحذف');
            return 1;
        }

        $keep = [
            'users', 'roles', 'permissions', 'role_has_permissions',
            'model_has_roles', 'model_has_permissions', 'migrations',
            'personal_access_tokens', 'password_reset_tokens',
            'sessions', 'cache', 'cache_locks', 'jobs',
            'job_batches', 'failed_jobs', 'sqlite_sequence',
        ];

        DB::statement('PRAGMA foreign_keys = OFF');

        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table'"))->pluck('name');

        foreach ($tables as $table) {
            if (!in_array($table, $keep)) {
                DB::table($table)->delete();
                $this->info("✅ Cleared: {$table}");
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');

        // إعادة شجرة الحسابات الأساسية
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\SettingSeeder', '--force' => true]);

        $this->info('');
        $this->info('🎉 تم مسح جميع البيانات وإعادة شجرة الحسابات بنجاح!');
        return 0;
    }
}
