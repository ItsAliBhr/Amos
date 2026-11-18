<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaReviewHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }
}
