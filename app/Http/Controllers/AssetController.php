<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\NavService;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\FundSetting;
use App\Models\InvestmentTransaction;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private NavService $nav)
    {
    }

    public function index(Request $request)
    {
        abort_unless(InvestmentController::memberCanViewFund($request), 403, 'نمایش این بخش برای اعضا غیرفعال است.');
        $state = $this->nav->state();
        $assets = Asset::with('category')->orderBy('name')->get();

        return view('assets.index', [
            'assets' => $assets,
            'state' => $state,
            'staleDays' => (int) FundSetting::current()->valuation_stale_days,
        ]);
    }

    public function show(Request $request, Asset $asset)
    {
        abort_unless(InvestmentController::memberCanViewFund($request), 403);
        $state = $this->nav->state();

        return view('assets.show', [
            'asset' => $asset->load('category'),
            'holding' => $state->holdings[$asset->id] ?? null,
            'valuations' => AssetValuation::where('asset_id', $asset->id)
                ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('status', 'approved'))
                ->orderByDesc('valuation_date')->orderByDesc('id')->paginate(20),
            'transactions' => InvestmentTransaction::where('asset_id', $asset->id)
                ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('status', 'approved'))
                ->orderByDesc('transaction_date')->limit(50)->get(),
            'realized' => InvestmentTransaction::where('asset_id', $asset->id)->where('status', 'approved')->sum('realized_pnl_irr'),
        ]);
    }
}
