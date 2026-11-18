<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
