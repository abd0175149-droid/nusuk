<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name', 'code', 'default_price_sar',
        'default_price_jod', 'description', 'is_active', 'account_id',
    ];

    protected $casts = [
        'default_price_sar' => 'decimal:2',
        'default_price_jod' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
