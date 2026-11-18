<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionType extends Model
{
    public const MONTHLY = 'monthly';
    public const EXTRA = 'extra';
    public const DONATION = 'donation';
    public const ADJUSTMENT = 'adjustment';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'counts_toward_cap' => 'boolean',
            'counts_toward_commitment' => 'boolean',
            'admin_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    public function isPool(): bool
    {
        return $this->ownership_type === OwnershipAccount::TYPE_POOL;
    }
}
