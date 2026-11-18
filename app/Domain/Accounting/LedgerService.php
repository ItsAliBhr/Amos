<?php

namespace App\Domain\Accounting;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ledger is the single source of financial truth. Transactions are append-only:
 * corrections are made through reversal documents, never by editing posted rows.
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: string, debit?: mixed, credit?: mixed, ownership_account_id?: int|null, cash_account_id?: int|null, asset_id?: int|null, quantity?: mixed, description?: string|null}>  $lines
     */
    public function post(
        string $type,
        ?Model $reference,
        DateTimeInterface|string $date,
        string $description,
        array $lines,
        string $idempotencyKey,
        ?User $actor,
        ?int $reversalOfId = null,
    ): LedgerTransaction {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Ledger postings must run inside a database transaction.');
        }

        if ($existing = LedgerTransaction::where('idempotency_key', $idempotencyKey)->first()) {
            throw new DuplicatePostingException('این سند قبلاً ثبت شده است.', $existing);
        }

        $debit = Money::zero();
        $credit = Money::zero();
        $normalized = [];
        foreach ($lines as $line) {
            $d = Money::irr($line['debit'] ?? 0);
            $c = Money::irr($line['credit'] ?? 0);
            if ($d->isNegative() || $c->isNegative()) {
                throw new RuntimeException('Ledger amounts must be non-negative.');
            }
            if ($d->isZero() && $c->isZero() && Money::of($line['quantity'] ?? 0)->isZero()) {
                continue;
            }
            $debit = $debit->plus($d);
            $credit = $credit->plus($c);
            $normalized[] = [
                'account_code' => $line['account'],
                'ownership_account_id' => $line['ownership_account_id'] ?? null,
                'cash_account_id' => $line['cash_account_id'] ?? null,
                'asset_id' => $line['asset_id'] ?? null,
                'quantity' => Money::str($line['quantity'] ?? 0, 8),
                'debit_irr' => (string) $d,
                'credit_irr' => (string) $c,
                'description' => $line['description'] ?? null,
            ];
        }

        if (! $debit->isEqualTo($credit)) {
            throw new RuntimeException("Unbalanced ledger transaction: debit {$debit} != credit {$credit}");
        }
        if ($normalized === []) {
            throw new RuntimeException('Empty ledger transaction.');
        }

        $tx = LedgerTransaction::create([
            'type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'transaction_date' => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date,
            'description' => mb_substr($description, 0, 500),
            'status' => 'posted',
            'created_by' => $actor?->id,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
            'reversal_of_id' => $reversalOfId,
            'idempotency_key' => $idempotencyKey,
        ]);

        foreach ($normalized as $row) {
            $tx->entries()->create($row);
        }

        return $tx;
    }

    /** Create a mirror document that cancels the given transaction. */
    public function reverse(LedgerTransaction $original, string $type, string $description, User $actor, DateTimeInterface|string|null $date = null): LedgerTransaction
    {
        if (LedgerTransaction::where('reversal_of_id', $original->id)->exists()) {
            throw new DuplicatePostingException('این سند قبلاً برگشت خورده است.', $original);
        }

        $lines = $original->entries->map(fn (LedgerEntry $e) => [
            'account' => $e->account_code,
            'debit' => $e->credit_irr,
            'credit' => $e->debit_irr,
            'ownership_account_id' => $e->ownership_account_id,
            'cash_account_id' => $e->cash_account_id,
            'asset_id' => $e->asset_id,
            'quantity' => Money::of($e->quantity)->negated(),
            'description' => 'برگشت: '.$e->description,
        ])->all();

        $tx = $this->post(
            $type,
            $original->reference,
            $date ?? now(),
            $description,
            $lines,
            'reversal:'.$original->id,
            $actor,
            $original->id,
        );

        $original->forceFill(['status' => 'reversed'])->saveQuietly();

        return $tx;
    }

    /** Sum of (debit - credit) for an account, optionally filtered. */
    public function balance(string $account, array $filters = []): string
    {
        $q = LedgerEntry::query()->where('account_code', $account);
        foreach ($filters as $col => $val) {
            $q->where($col, $val);
        }

        return (string) Money::irr($q->sum(DB::raw('debit_irr - credit_irr')) ?: 0);
    }

    /** Verify every posted ledger transaction is balanced. Returns list of offending ids. */
    public function verifyIntegrity(): array
    {
        return LedgerEntry::query()
            ->select('ledger_transaction_id')
            ->groupBy('ledger_transaction_id')
            ->havingRaw('SUM(debit_irr) <> SUM(credit_irr)')
            ->pluck('ledger_transaction_id')
            ->all();
    }
}
