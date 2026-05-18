<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolationType extends Model
{
    protected $fillable = ['name', 'code', 'default_cost_sar', 'description', 'is_active'];

    protected $casts = [
        'default_cost_sar' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }
}
