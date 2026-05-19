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
            'icon' => $options['icon'] ?? null,
            'action_url' => $options['action_url'] ?? null,
            'data' => $options['data'] ?? null,
            'is_read' => false,
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
     * إشعار عند اعتماد فاتورة
     */
    public static function invoiceCreated($invoice): void
    {
        self::broadcast(
            'فاتورة جديدة',
            "تم اعتماد الفاتورة {$invoice->invoice_number} بمبلغ " . number_format($invoice->total_jod, 3) . ' JOD',
            [
                'type' => 'invoice',
                'icon' => '🧾',
                'action_url' => "/invoices/{$invoice->id}",
                'data' => ['reference_type' => 'invoice', 'reference_id' => $invoice->id],
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
                'type' => 'transfer',
                'icon' => '💱',
                'action_url' => "/transfers",
                'data' => ['reference_type' => 'transfer', 'reference_id' => $transfer->id],
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
                'type' => 'expense',
                'icon' => '💰',
                'action_url' => "/expenses",
                'data' => ['reference_type' => 'expense', 'reference_id' => $expense->id],
            ],
            auth()->id()
        );
    }

    /**
     * إشعار عند اعتماد سند قبض
     */
    public static function receiptCreated($receipt): void
    {
        self::broadcast(
            'سند قبض معتمد',
            "تم اعتماد سند القبض {$receipt->receipt_number} بمبلغ " . number_format($receipt->amount_jod, 3) . ' JOD',
            [
                'type' => 'receipt',
                'icon' => '📄',
                'action_url' => "/receipts",
                'data' => ['reference_type' => 'receipt', 'reference_id' => $receipt->id],
            ],
            auth()->id()
        );
    }
}
