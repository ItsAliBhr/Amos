<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'فعال',
        self::STATUS_INACTIVE => 'غیرفعال',
        self::STATUS_SUSPENDED => 'تعلیق',
    ];

    public const ROLES = [
        self::ROLE_ADMIN => 'مدیر',
        self::ROLE_MEMBER => 'عضو',
    ];

    protected $fillable = [
        'name', 'username', 'email', 'mobile', 'password', 'role', 'status',
        'must_change_password', 'monthly_commitment_irr', 'joined_at', 'admin_note', 'avatar_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'monthly_commitment_irr' => 'decimal:2',
            'joined_at' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function ownershipAccount(): HasOne
    {
        return $this->hasOne(OwnershipAccount::class, 'owner_id')->where('owner_type', OwnershipAccount::TYPE_MEMBER);
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class, 'member_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeMembers($q)
    {
        // Every user (admins included) may own a personal portfolio.
        return $q;
    }

    public function effectiveMonthlyCommitment(): string
    {
        if ($this->monthly_commitment_irr !== null) {
            return (string) $this->monthly_commitment_irr;
        }

        return (string) FundSetting::current()->default_monthly_commitment_irr;
    }

    public function hasFinancialHistory(): bool
    {
        return $this->contributions()->exists()
            || WithdrawalRequest::where('member_id', $this->id)->exists()
            || ($this->ownershipAccount && InvestmentUnit::where('ownership_account_id', $this->ownershipAccount->id)->exists());
    }
}
