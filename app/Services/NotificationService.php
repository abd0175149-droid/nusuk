<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * إرسال إشعار لمستخدم واحد
     */
    public static function send(int $userId, string $title, string $body, array $options = []): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $options['type'] ?? 'info',
            'action_url' => $options['action_url'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
        ]);
    }

    /**
     * إرسال إشعار لعدة مستخدمين
     */
    public static function sendToMany(array $userIds, string $title, string $body, array $options = []): void
    {
        foreach ($userIds as $userId) {
            self::send($userId, $title, $body, $options);
        }
    }

    /**
     * إرسال إشعار لكل المستخدمين ما عدا المرسِل
     */
    public static function broadcast(string $title, string $body, array $options = [], ?int $excludeUserId = null): void
    {
        $users = User::query();
        if ($excludeUserId) {
            $users->where('id', '!=', $excludeUserId);
        }
        $userIds = $users->pluck('id')->toArray();
        self::sendToMany($userIds, $title, $body, $options);
    }

    /**
     * إشعار عند إنشاء فاتورة
     */
    public static function invoiceCreated($invoice): void
    {
        self::broadcast(
            'فاتورة جديدة',
            "تم إنشاء فاتورة {$invoice->invoice_number} بمبلغ " . number_format($invoice->total_jod, 3) . ' JOD',
            [
                'type' => 'info',
                'action_url' => "/invoices/{$invoice->id}",
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
            ],
            auth()->id()
        );
    }

    /**
     * إشعار عند اعتماد حوالة
     */
    public static function transferApproved($transfer): void
    {
        self::broadcast(
            'حوالة معتمدة',
            "تم اعتماد الحوالة {$transfer->transfer_number} بمبلغ " . number_format($transfer->amount_sar, 2) . ' SAR',
            [
                'type' => 'success',
                'action_url' => "/transfers",
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
            ],
            auth()->id()
        );
    }

    /**
     * إشعار عند اعتماد مصروف
     */
    public static function expenseApproved($expense): void
    {
        self::broadcast(
            'مصروف معتمد',
            "تم اعتماد المصروف {$expense->expense_number} بمبلغ " . number_format($expense->amount, 2) . " {$expense->currency}",
            [
                'type' => 'warning',
                'action_url' => "/expenses",
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
            ],
            auth()->id()
        );
    }

    /**
     * إشعار عند إنشاء سند قبض
     */
    public static function receiptCreated($receipt): void
    {
        self::broadcast(
            'سند قبض جديد',
            "تم إنشاء سند قبض {$receipt->receipt_number} بمبلغ " . number_format($receipt->amount_jod, 3) . ' JOD',
            [
                'type' => 'info',
                'action_url' => "/receipts",
                'reference_type' => 'receipt',
                'reference_id' => $receipt->id,
            ],
            auth()->id()
        );
    }
}
