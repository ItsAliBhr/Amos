<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetValuation;
use App\Models\CashAccount;
use App\Models\Contribution;
use App\Models\InvestmentTransaction;
use App\Models\WithdrawalRequest;

class ApprovalQueueController extends Controller
{
    public function index()
    {
        return view('admin.approvals', [
            'contributions' => Contribution::with(['member', 'type', 'ownershipAccount', 'destinationAccount'])->where('status', 'pending')->oldest()->get(),
            'investments' => InvestmentTransaction::with(['asset', 'cashAccount'])->where('status', 'pending')->oldest()->get(),
            'valuations' => AssetValuation::with('asset')->where('status', 'pending')->oldest()->get(),
            'withdrawals' => WithdrawalRequest::with(['member', 'ownershipAccount'])->whereIn('status', ['pending', 'approved', 'awaiting_liquidity'])->oldest('requested_at')->get(),
            'accounts' => CashAccount::active()->get(),
        ]);
    }
}
