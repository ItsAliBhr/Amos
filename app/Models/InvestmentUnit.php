<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentUnit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'units' => 'decimal:8',
            'nav_per_unit_irr' => 'decimal:8',
            'amount_irr' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public function ownershipAccount()
    {
        return $this->belongsTo(OwnershipAccount::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
