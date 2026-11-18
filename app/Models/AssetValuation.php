<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetValuation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valuation_date' => 'date',
            'unit_price_irr' => 'decimal:8',
            'total_quantity' => 'decimal:8',
            'total_value_irr' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
