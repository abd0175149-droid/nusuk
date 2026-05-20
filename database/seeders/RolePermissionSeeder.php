<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء الأدوار
        $admin = Role::create(['name' => 'مدير عام', 'slug' => 'admin', 'description' => 'صلاحيات كاملة']);
        $sales = Role::create(['name' => 'موظف مبيعات', 'slug' => 'sales', 'description' => 'إدخال عمليات']);
        $accountant = Role::create(['name' => 'محاسب', 'slug' => 'accountant', 'description' => 'عمليات مالية محدودة']);
        $hrManager = Role::create(['name' => 'مدير موارد بشرية', 'slug' => 'hr_manager', 'description' => 'إدارة الموظفين والحضور والرواتب']);

        // تعريف الصلاحيات لكل وحدة
        $modules = [
            'agents' => ['view', 'create', 'update', 'delete'],
            'clients' => ['view', 'create', 'update', 'delete'],
            'services' => ['view', 'create', 'update', 'delete'],
            'violation_types' => ['view', 'create', 'update', 'delete'],
            'transfers' => ['view', 'create', 'approve', 'reject'],
            'receipts' => ['view', 'create', 'approve', 'reject'],
            'expenses' => ['view', 'create', 'approve', 'reject'],
            'violations' => ['view', 'create', 'approve', 'reject'],
            'invoices' => ['view', 'create', 'update', 'submit', 'approve', 'reject'],
            'reports' => ['view'],
            'settings' => ['view', 'update'],
            'users' => ['view', 'create', 'update', 'delete'],

            // HR Module
            'employees' => ['view', 'create', 'update', 'delete'],
            'attendance' => ['view', 'create', 'manual_edit'],
            'leaves' => ['view', 'create', 'approve', 'reject'],
            'advances' => ['view', 'create', 'approve', 'reject'],
            'penalties' => ['view', 'create'],
            'payroll' => ['view', 'generate', 'approve', 'reject'],
            'hr_reports' => ['view'],
        ];

        $allPermissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $perm = Permission::create([
                    'name' => ucfirst($action) . ' ' . str_replace('_', ' ', $module),
                    'slug' => "{$module}.{$action}",
                    'module' => $module,
                ]);
                $allPermissions["{$module}.{$action}"] = $perm->id;
            }
        }

        // صلاحيات موظف المبيعات
        $salesPerms = [
            'agents.view', 'clients.view', 'services.view', 'violation_types.view',
            'transfers.view', 'transfers.create',
            'receipts.view', 'receipts.create',
            'violations.view', 'violations.create',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.submit',
            'reports.view',
        ];

        // صلاحيات المحاسب
        $accountantPerms = [
            'agents.view', 'clients.view', 'services.view', 'violation_types.view',
            'transfers.view', 'transfers.create',
            'receipts.view', 'receipts.create',
            'expenses.view', 'expenses.create',
            'reports.view',
        ];

        foreach ($salesPerms as $slug) {
            if (isset($allPermissions[$slug])) {
                $sales->permissions()->attach($allPermissions[$slug]);
            }
        }

        foreach ($accountantPerms as $slug) {
            if (isset($allPermissions[$slug])) {
                $accountant->permissions()->attach($allPermissions[$slug]);
            }
        }

        // صلاحيات مدير الموارد البشرية
        $hrPerms = [
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'attendance.view', 'attendance.create', 'attendance.manual_edit',
            'leaves.view', 'leaves.create', 'leaves.approve', 'leaves.reject',
            'advances.view', 'advances.create', 'advances.approve', 'advances.reject',
            'penalties.view', 'penalties.create',
            'payroll.view', 'payroll.generate', 'payroll.approve', 'payroll.reject',
            'hr_reports.view',
        ];

        foreach ($hrPerms as $slug) {
            if (isset($allPermissions[$slug])) {
                $hrManager->permissions()->attach($allPermissions[$slug]);
            }
        }
    }
}
