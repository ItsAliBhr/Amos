<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounting\AccountCode;
use App\Domain\Accounting\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    public function index(Request $request, LedgerService $ledger)
    {
        $balances = LedgerEntry::groupBy('account_code')
            ->selectRaw('account_code, SUM(debit_irr) AS d, SUM(credit_irr) AS c')->get();

        return view('admin.ledger.index', [
            'transactions' => LedgerTransaction::with('creator')
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'balances' => $balances,
            'unbalanced' => $ledger->verifyIntegrity(),
            'totals' => LedgerEntry::selectRaw('SUM(debit_irr) AS d, SUM(credit_irr) AS c')->first(),
        ]);
    }

    public function show(LedgerTransaction $ledgerTransaction)
    {
        $ledgerTransaction->load(['entries.ownershipAccount.owner', 'entries.cashAccount', 'entries.asset', 'creator', 'approver']);

        return view('admin.ledger.show', ['tx' => $ledgerTransaction]);
    }
}
