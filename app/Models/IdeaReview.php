<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaReview extends Model
{
    protected $guarded = ['id'];

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }
}
