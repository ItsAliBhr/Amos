<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'decimal_places' => 'integer'];
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function valuations()
    {
        return $this->hasMany(AssetValuation::class);
    }

    public function transactions()
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function latestApprovedValuation(): ?AssetValuation
    {
        return $this->valuations()->where('status', 'approved')
            ->orderByDesc('valuation_date')->orderByDesc('id')->first();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
