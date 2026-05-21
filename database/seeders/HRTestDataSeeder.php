<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\Advance;
use App\Models\ViolationType;
use App\Models\EmployeePenalty;
use App\Models\Attendance;
use App\Services\NumberingService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class HRTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. الأقسام (Departments)
            $itDept = Department::firstOrCreate(['name' => 'تقنية المعلومات'], ['code' => 'IT']);
            $salesDept = Department::firstOrCreate(['name' => 'المبيعات'], ['code' => 'SALES']);

            // 2. الورديات (Shifts)
            $morningShift = Shift::firstOrCreate(
                ['name' => 'الوردية الصباحية'],
                [
                    'code' => 'MORN',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'grace_minutes' => 15,
                    'working_days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'],
                    'break_minutes' => 60,
                    'is_flexible' => false,
                    'min_hours' => 8,
                    'country' => 'SA',
                ]
            );

            // 3. إنشاء مستخدم وموظف تجريبي (Employee)
            $user1 = User::firstOrCreate(
                ['email' => 'emp1@nusuk.test'],
                [
                    'name' => 'أحمد الموظف',
                    'password' => Hash::make('password'),
                ]
            );
            $user1->assignRole('موظف مبيعات');

            $employee1 = Employee::firstOrCreate(
                ['user_id' => $user1->id],
                [
                    'employee_number' => NumberingService::generate('EMP'),
                    'department_id' => $salesDept->id,
                    'shift_id' => $morningShift->id,
                    'currency' => 'SAR',
                    'basic_salary' => 4000,
                    'housing_allowance' => 1000,
                    'transport_allowance' => 500,
                    'other_allowance' => 0,
                    'hire_date' => Carbon::now()->subMonths(6)->toDateString(),
                    'status' => 'active',
                    'overtime_calc_method' => 'multiplier',
                    'overtime_multiplier' => 1.5,
                ]
            );

            // 4. الحضور والانصراف (Attendance) للأيام الخمسة الماضية
            $today = Carbon::today();
            for ($i = 1; $i <= 5; $i++) {
                $date = $today->copy()->subDays($i);
                if (!in_array($date->format('l'), $morningShift->working_days)) continue;

                Attendance::firstOrCreate([
                    'employee_id' => $employee1->id,
                    'date' => $date->toDateString(),
                ], [
                    'check_in' => $date->copy()->setTime(9, 5, 0)->toTimeString(), // متأخر 5 دقائق (ضمن السماح 15)
                    'check_out' => $date->copy()->setTime(17, 30, 0)->toTimeString(), // عمل إضافي 30 دقيقة
                    'status' => 'present',
                    'late_minutes' => 0,
                    'early_leave_minutes' => 0,
                    'overtime_minutes' => 30,
                    'total_hours' => 8.41, // تقريباً
                ]);
            }

            // 5. أنواع الإجازات وطلب إجازة
            $annualLeave = LeaveType::firstOrCreate(['code' => 'ANNUAL'], [
                'name' => 'إجازة سنوية',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 21,
            ]);

            $leaveReq = LeaveRequest::firstOrCreate([
                'employee_id' => $employee1->id,
                'leave_type_id' => $annualLeave->id,
                'start_date' => $today->copy()->addDays(2)->toDateString(),
                'end_date' => $today->copy()->addDays(3)->toDateString(),
            ], [
                'reason' => 'ظرف عائلي',
                'status' => 'pending'
            ]);
            // اعتماد الإجازة لخصم الرصيد
            if ($leaveReq->status === 'pending') {
                $leaveReq->approve(User::first());
            }

            // 6. طلب سلفة (Advance)
            $advance = Advance::firstOrCreate([
                'employee_id' => $employee1->id,
                'amount' => 1000,
                'installments_count' => 2,
            ], [
                'advance_number' => NumberingService::generate('ADV'),
                'currency' => 'SAR',
                'installment_amount' => 500,
                'remaining_amount' => 1000,
                'payment_method' => 'cash',
                'reason' => 'سلفة طارئة',
                'status' => 'pending',
                'created_by' => $user1->id,
            ]);

            // اعتماد السلفة يدوياً لتجنب مشاكل الصلاحيات في الـ CLI
            if ($advance->status === 'pending') {
                $advance->approve(User::first() ?? $user1);
                
                $startDate = Carbon::now()->addMonth()->startOfMonth();
                for ($i = 0; $i < $advance->installments_count; $i++) {
                    $installmentDate = $startDate->copy()->addMonths($i);
                    $amount = ($i === $advance->installments_count - 1)
                        ? $advance->amount - ($advance->installment_amount * ($advance->installments_count - 1))
                        : $advance->installment_amount;

                    \App\Models\AdvanceInstallment::firstOrCreate([
                        'advance_id' => $advance->id,
                        'month' => $installmentDate->month,
                        'year' => $installmentDate->year,
                    ], [
                        'employee_id' => $advance->employee_id,
                        'amount' => $amount,
                        'is_paid' => false,
                    ]);
                }
                // المحاسبة
                \App\Services\AccountingService::recordAdvance($advance);
            }

            // 7. المخالفات (Penalties)
            $violationType = ViolationType::firstOrCreate(['name' => 'تأخير متكرر'], ['severity' => 'medium']);
            EmployeePenalty::firstOrCreate([
                'employee_id' => $employee1->id,
                'violation_type_id' => $violationType->id,
                'penalty_type' => 'deduction',
            ], [
                'penalty_date' => $today->toDateString(),
                'deduction_amount' => 100,
                'reason' => 'تأخير متكرر رغم التنبيه',
                'is_deducted' => false,
                'created_by' => User::first()->id ?? $user1->id,
            ]);

            // 8. إنشاء مسير رواتب تجريبي للشهر الحالي
            // ملاحظة: لا نستدعي Controller لتوليد المسير مباشرة بل السيرفيس لتبسيط الاختبار
            try {
                $payroll = PayrollService::generate($today->month, $today->year, 'SAR');
                \App\Services\AccountingService::recordPayroll($payroll);
            } catch (\Exception $e) {
                // إذا كان المسير موجود مسبقاً، نتجاهل الخطأ
            }
        });
    }
}
