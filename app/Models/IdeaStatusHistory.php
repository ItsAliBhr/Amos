<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
