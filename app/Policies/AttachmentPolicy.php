<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Contribution;
use App\Models\Idea;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $a): bool
    {
        if ($user->isAdmin() || $a->uploaded_by === $user->id) {
            return true;
        }
        $related = $a->related;
        if ($related instanceof Contribution) {
            return $related->member_id === $user->id;
        }
        if ($related instanceof Idea) {
            return $related->status !== 'draft';
        }

        return false; // investment documents etc. are admin-only
    }
}
