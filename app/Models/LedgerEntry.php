<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'debit_irr' => 'decimal:2',
            'credit_irr' => 'decimal:2',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function ownershipAccount()
    {
        return $this->belongsTo(OwnershipAccount::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
