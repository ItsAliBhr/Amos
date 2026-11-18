<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnershipAccount extends Model
{
    public const TYPE_MEMBER = 'member';
    public const TYPE_POOL = 'pool';

    protected $guarded = ['id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function units()
    {
        return $this->hasMany(InvestmentUnit::class);
    }

    public function isPool(): bool
    {
        return $this->owner_type === self::TYPE_POOL;
    }

    public static function pool(): self
    {
        return static::firstOrCreate(
            ['owner_type' => self::TYPE_POOL, 'owner_id' => null],
            ['name' => 'سبد مشترک (Pool)', 'status' => 'active']
        );
    }

    public static function forMember(User $user): self
    {
        return static::firstOrCreate(
            ['owner_type' => self::TYPE_MEMBER, 'owner_id' => $user->id],
            ['name' => 'سبد '.$user->name, 'status' => 'active']
        );
    }

    public function displayName(): string
    {
        return $this->isPool() ? 'سبد مشترک (Pool)' : ($this->owner?->name ?? $this->name);
    }
}
