<?php

namespace App\Policies;

use App\Models\Idea;
use App\Models\User;

class IdeaPolicy
{
    public function view(User $user, Idea $idea): bool
    {
        return $idea->status !== 'draft' || $idea->proposed_by === $user->id;
    }

    public function update(User $user, Idea $idea): bool
    {
        return $idea->proposed_by === $user->id && in_array($idea->status, ['draft', 'needs_information'], true);
    }
}
