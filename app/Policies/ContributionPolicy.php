<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    public function view(User $user, Contribution $c): bool
    {
        return $user->isAdmin() || $c->member_id === $user->id || $c->submitted_by === $user->id;
    }

    public function cancel(User $user, Contribution $c): bool
    {
        return $user->isAdmin() || $c->submitted_by === $user->id;
    }

    public function approve(User $user): bool
    {
        return $user->isAdmin();
    }
}
