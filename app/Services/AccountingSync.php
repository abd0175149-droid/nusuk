<?php

namespace App\Services;

class AccountingSync
{
    /**
     * Prevents infinite loops during two-way synchronization.
     * True means a sync is currently in progress.
     */
    public static bool $isSyncing = false;
}
