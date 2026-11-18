<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribution extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVERSED = 'reversed';

    public const PAYMENT_METHODS = [
        'card' => 'کارت به کارت',
        'transfer' => 'انتقال بانکی (پایا/ساتنا)',
        'cash' => 'نقدی',
        'other' => 'سایر',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_irr' => 'decimal:2',
            'contribution_date' => 'date',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
            'exceeds_cap' => 'boolean',
            'cap_override' => 'boolean',
            'units_issued' => 'decimal:8',
            'nav_per_unit_irr' => 'decimal:8',
        ];
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function type()
    {
        return $this->belongsTo(ContributionType::class, 'type_id');
    }

    public function ownershipAccount()
    {
        return $this->belongsTo(OwnershipAccount::class);
    }

    public function destinationAccount()
    {
        return $this->belongsTo(CashAccount::class, 'destination_account_id');
    }

    public function receipt()
    {
        return $this->belongsTo(Attachment::class, 'receipt_attachment_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function ledgerTransaction()
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function isPool(): bool
    {
        return $this->ownershipAccount?->isPool() ?? false;
    }
}
