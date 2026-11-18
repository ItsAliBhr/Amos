<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WithdrawalRequest;

class WithdrawalRequestPolicy
{
    public function view(User $user, WithdrawalRequest $w): bool
    {
        return $user->isAdmin() || ($w->member_id !== null && $w->member_id === $user->id);
    }

    public function cancel(User $user, WithdrawalRequest $w): bool
    {
        return $user->isAdmin() || ($w->member_id !== null && $w->member_id === $user->id);
    }
}
