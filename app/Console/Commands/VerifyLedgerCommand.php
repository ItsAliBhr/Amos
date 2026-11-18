<?php

namespace App\Console\Commands;

use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\NavService;
use App\Models\InvestmentUnit;
use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Console\Command;

class VerifyLedgerCommand extends Command
{
    protected $signature = 'amos:verify-ledger {--snapshot : Also record today\'s NAV snapshot}';

    protected $description = 'Check that every ledger document is balanced and print the NAV rebuilt from the ledger';

    public function handle(LedgerService $ledger, NavService $nav): int
    {
        $bad = $ledger->verifyIntegrity();
        $totals = LedgerEntry::selectRaw('SUM(debit_irr) d, SUM(credit_irr) c')->first();
        $state = $nav->state();
        $this->table(['Metric', 'Value (IRR)'], [
            ['Total debit', Money::str($totals->d ?? 0)],
            ['Total credit', Money::str($totals->c ?? 0)],
            ['Cash', (string) $state->cash],
            ['Assets (market)', (string) $state->assetsMarket],
            ['NAV', (string) $state->nav],
            ['Total units', (string) Money::of(InvestmentUnit::sum('units'))],
            ['NAV per unit', (string) $state->navPerUnit],
        ]);
        if ($this->option('snapshot')) {
            $nav->recordSnapshot($state);
        }
        if ($bad) {
            $this->error('Unbalanced ledger transactions: '.implode(', ', $bad));

            return self::FAILURE;
        }
        $this->info('Ledger is balanced.');

        return self::SUCCESS;
    }
}
