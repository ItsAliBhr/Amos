<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Notifier
{
    public static function user(int|User $user, string $type, string $title, ?string $body = null, ?Model $related = null): void
    {
        $id = $user instanceof User ? $user->id : $user;
        AppNotification::create([
            'user_id' => $id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_type' => $related ? $related->getMorphClass() : null,
            'related_id' => $related?->getKey(),
        ]);
    }

    public static function admins(string $type, string $title, ?string $body = null, ?Model $related = null, ?int $exceptUserId = null): void
    {
        User::where('role', User::ROLE_ADMIN)->active()
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->pluck('id')
            ->each(fn ($id) => self::user($id, $type, $title, $body, $related));
    }

    public static function allActive(string $type, string $title, ?string $body = null, ?Model $related = null, ?int $exceptUserId = null): void
    {
        User::active()
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->pluck('id')
            ->each(fn ($id) => self::user($id, $type, $title, $body, $related));
    }
}
